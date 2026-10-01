import { useState } from 'react';
import { Toggle, Button } from './ui';

/**
 * Schema form – every create/edit dialog in the panel uses this.
 * field: { name, label, type: text|number|email|password|textarea|select|multiselect|toggle|date|datetime|file|chips|color, options:[{value,label}], hint, required, show:(values)=>bool, cols }
 */
export function Field({ f, value, onChange, error }) {
  const common = { id: f.name, className: 'input', value: value ?? '', required: f.required, placeholder: f.placeholder, onChange: (e) => onChange(e.target.value) };
  let input;
  switch (f.type) {
    case 'textarea': input = <textarea {...common} rows={f.rows || 4} />; break;
    case 'select':
      input = (
        <select {...common}>
          {!f.required && <option value="">{f.empty || '—'}</option>}
          {(f.options || []).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      );
      break;
    case 'multiselect': {
      const v = (value || []).map(String);
      input = (
        <div className="chips">
          {(f.options || []).map((o) => (
            <button type="button" key={o.value} className={`chip ${v.includes(String(o.value)) ? 'on' : ''}`}
              onClick={() => onChange(v.includes(String(o.value)) ? (value || []).filter((x) => String(x) !== String(o.value)) : [...(value || []), o.value])}>{o.label}</button>
          ))}
          {!f.options?.length && <span className="muted small">No options</span>}
        </div>
      );
      break;
    }
    case 'toggle': input = <div className="row"><Toggle on={!!value} onChange={onChange} label={f.label} /><span className="small muted">{value ? f.onText || 'On' : f.offText || 'Off'}</span></div>; break;
    case 'file': input = <input type="file" className="input" accept={f.accept} onChange={(e) => onChange(e.target.files[0])} />; break;
    case 'datetime': input = <input {...common} type="datetime-local" value={value ? String(value).slice(0, 16) : ''} />; break;
    case 'date': input = <input {...common} type="date" value={value ? String(value).slice(0, 10) : ''} />; break;
    case 'chips': {
      const [t, setT] = [value || [], onChange];
      input = (
        <div className="col" style={{ gap: 6 }}>
          <div className="chips">{t.map((x, i) => <span key={i} className="chip on" onClick={() => setT(t.filter((_, j) => j !== i))}>{x} ✕</span>)}</div>
          <input className="input" placeholder={f.placeholder || 'Type and press Enter'} onKeyDown={(e) => { if (e.key === 'Enter' && e.target.value.trim()) { e.preventDefault(); setT([...t, e.target.value.trim()]); e.target.value = ''; } }} />
        </div>
      );
      break;
    }
    default: input = <input {...common} type={f.type || 'text'} step={f.step} min={f.min} />;
  }
  return (
    <div className="field" style={f.cols === 2 ? { gridColumn: '1 / -1' } : undefined}>
      {f.label && <label htmlFor={f.name}>{f.label}{f.required && ' *'}</label>}
      {input}
      {error ? <div className="err">{Array.isArray(error) ? error[0] : error}</div> : f.hint && <div className="hint">{f.hint}</div>}
    </div>
  );
}

export function SchemaForm({ fields, initial = {}, onSubmit, submitText = 'Save', loading, errors = {}, columns = 2, children, onCancel }) {
  const [v, setV] = useState(initial);
  const set = (k) => (x) => setV((s) => ({ ...s, [k]: x }));
  return (
    <form onSubmit={(e) => { e.preventDefault(); onSubmit(v); }} className="col">
      <div className={`grid ${columns === 2 ? 'g2' : ''}`}>
        {fields.filter((f) => !f.show || f.show(v)).map((f, i) => <Field key={`${f.name}-${i}`} f={f} value={v[f.name]} onChange={set(f.name)} error={errors[f.name]} />)}
      </div>
      {children?.(v, setV)}
      <div className="row" style={{ justifyContent: 'flex-end' }}>
        {onCancel && <Button type="button" onClick={onCancel}>Cancel</Button>}
        <Button variant="primary" type="submit" loading={loading}>{submitText}</Button>
      </div>
    </form>
  );
}

/** Keeps server validation errors for the form after a failed mutation. */
export function useFormErrors() {
  const [errors, setErrors] = useState({});
  const run = async (promise) => {
    setErrors({});
    try {
      return await promise.unwrap();
    } catch (e) {
      setErrors(e.errors || {});
      throw e;
    }
  };
  return [errors, run];
}
