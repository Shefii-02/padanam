import { useEffect, useState } from 'react';
import { useWaSettingsQuery, useSaveWaAccountMutation, useSaveWaTemplatesMutation, useTestWaMutation, useWaMessagesQuery, useRetryWaMutation, useSendWaMutation } from './api';
import { Badge, Button, Can, Card, Modal, PageHead, Spinner, Stat, Tabs, Toggle, fmtDate, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field } from '../../components/Form';

const pretty = (o) => (o ? JSON.stringify(o, null, 2) : '');

/** API connection form – used for both "share" (links, invoices) and "otp". */
function Connection({ purpose, acc, presets, placeholders }) {
  const [save, s] = useSaveWaAccountMutation();
  const [test, t] = useTestWaMutation();
  const [v, setV] = useState(null);
  const [err, setErr] = useState({});
  const [phone, setPhone] = useState('');
  const isOtp = purpose === 'otp';

  useEffect(() => {
    setV({ ...acc, token: '', text_body: pretty(acc.text_body), document_body: pretty(acc.document_body) });
  }, [acc]);
  if (!v) return <Spinner />;
  const set = (k) => (x) => setV((o) => ({ ...o, [k]: x }));

  const applyPreset = (key) => {
    const p = presets[key];
    if (!p) return;
    setV((o) => ({ ...o, provider: key, api_url: p.api_url, document_url: p.document_url || '', auth_type: p.auth_type, auth_key: p.auth_key, body_format: p.body_format,
      success_path: p.success_path || '', text_body: pretty(p.text_body), document_body: pretty(p.document_body) }));
  };

  const submit = async () => {
    const e = {};
    const parse = (k, required) => {
      if (!v[k]?.trim()) { if (required) e[k] = 'Required'; return null; }
      try { return JSON.parse(v[k]); } catch { e[k] = 'Not valid JSON'; return null; }
    };
    const text_body = parse('text_body', true);
    const document_body = isOtp ? null : parse('document_body', false);
    setErr(e);
    if (Object.keys(e).length) return;
    try {
      await save({
        purpose, provider: v.provider, enabled: !!v.enabled, api_url: v.api_url || null, document_url: v.document_url || null,
        token: v.token || undefined, clear_token: v.clear_token || undefined, auth_type: v.auth_type, auth_key: v.auth_key, body_format: v.body_format,
        text_body, document_body, success_path: v.success_path || null, country_code: v.country_code,
        message_template: isOtp ? v.message_template : undefined, fallback_sms: isOtp ? !!v.fallback_sms : undefined,
      }).unwrap();
      setV((o) => ({ ...o, token: '' }));
    } catch (x) { setErr(Object.fromEntries(Object.entries(x.errors || {}).map(([k, m]) => [k, m[0]]))); }
  };

  return (
    <div className="grid" style={{ gridTemplateColumns: 'minmax(0,1fr) 300px', alignItems: 'start' }}>
      <Card title={isOtp ? 'WhatsApp OTP API' : 'WhatsApp share API'} actions={<Badge tone={acc.ready ? 'green' : 'grey'}>{acc.ready ? 'connected & on' : acc.enabled ? 'incomplete' : 'off'}</Badge>}>
        <div className="row between card" style={{ background: isOtp ? 'var(--peach)' : 'var(--mint)', border: 0, marginBottom: 14 }}>
          <div>
            <div className="b">{isOtp ? 'Send login OTP on WhatsApp' : 'Send payment links, invoices & admission messages through this API'}</div>
            <div className="small muted">{isOtp ? 'When OFF, OTP goes by SMS. When ON and WhatsApp fails, SMS is used if “fallback” is on.' : 'When OFF, staff can still share using the WhatsApp app (wa.me link).'}</div>
          </div>
          <Toggle on={!!v.enabled} onChange={set('enabled')} label="Enabled" />
        </div>
        <div className="grid g2">
          <Field f={{ name: 'provider', label: 'Start from a preset', type: 'select', options: Object.entries(presets).filter(([k]) => (isOtp ? true : k !== 'meta_cloud_otp')).map(([value, p]) => ({ value, label: p.label })), hint: 'Fills the fields below – change INSTANCE_ID / PHONE_NUMBER_ID in the URL' }}
            value={v.provider} onChange={applyPreset} />
          <Field f={{ name: 'country_code', label: 'Country code', hint: '91 for India' }} value={v.country_code} onChange={set('country_code')} error={err.country_code} />
          <Field f={{ name: 'api_url', label: isOtp ? 'API URL' : 'API URL (text messages)', cols: 2, placeholder: 'https://…' }} value={v.api_url} onChange={set('api_url')} error={err.api_url} />
          {!isOtp && <Field f={{ name: 'document_url', label: 'API URL for files / PDF (optional)', cols: 2, hint: 'Leave empty if the same URL sends files' }} value={v.document_url} onChange={set('document_url')} error={err.document_url} />}
          <Field f={{ name: 'token', label: 'API token', type: 'password', cols: 2, placeholder: acc.has_token ? `Saved ${acc.token_hint} – type to replace` : 'Paste your token', hint: 'Stored encrypted in the database. It is never shown again.' }}
            value={v.token} onChange={set('token')} error={err.token} />
          <Field f={{ name: 'auth_type', label: 'How the token is sent', type: 'select', required: true, options: [
            { value: 'bearer', label: 'Authorization: Bearer <token>' }, { value: 'header', label: 'Custom header' }, { value: 'query', label: 'URL parameter (?token=)' }, { value: 'body', label: 'Field in the request body' }] }}
            value={v.auth_type} onChange={set('auth_type')} />
          <Field f={{ name: 'auth_key', label: 'Header / parameter / field name', hint: 'e.g. token, apikey, X-API-KEY' }} value={v.auth_key} onChange={set('auth_key')} />
          <Field f={{ name: 'body_format', label: 'Request body format', type: 'select', required: true, options: [{ value: 'json', label: 'JSON' }, { value: 'form', label: 'Form (x-www-form-urlencoded)' }] }} value={v.body_format} onChange={set('body_format')} />
          <Field f={{ name: 'success_path', label: 'Success check (optional)', placeholder: 'messages.0.id', hint: 'A JSON field in the reply that must exist' }} value={v.success_path} onChange={set('success_path')} />
          <Field f={{ name: 'text_body', label: isOtp ? 'Request body (JSON)' : 'Text message body (JSON)', type: 'textarea', rows: 8, cols: isOtp ? 2 : undefined }} value={v.text_body} onChange={set('text_body')} error={err.text_body} />
          {!isOtp && <Field f={{ name: 'document_body', label: 'File / PDF message body (JSON)', type: 'textarea', rows: 8 }} value={v.document_body} onChange={set('document_body')} error={err.document_body} />}
          {isOtp && <>
            <Field f={{ name: 'message_template', label: 'OTP message', type: 'textarea', rows: 2, cols: 2, hint: 'Must contain {{otp}}. Used as {{message}} in the body. (Meta requires an approved template – use the Meta OTP preset.)' }}
              value={v.message_template} onChange={set('message_template')} error={err.message_template} />
            <Field f={{ name: 'fallback_sms', label: 'If WhatsApp fails, send the OTP by SMS', type: 'toggle', cols: 2 }} value={v.fallback_sms} onChange={set('fallback_sms')} />
          </>}
        </div>
        <div className="row between" style={{ marginTop: 14 }}>
          {acc.has_token ? <label className="row small"><input type="checkbox" checked={!!v.clear_token} onChange={(e) => set('clear_token')(e.target.checked)} /> Remove saved token</label> : <span />}
          <Can perm="whatsapp.manage"><Button variant="primary" loading={s.isLoading} onClick={submit}>Save</Button></Can>
        </div>
      </Card>
      <div className="col">
        <Card title="Send a test">
          <p className="small muted" style={{ marginTop: 0 }}>Save first, then send a test to your own number.{isOtp && ' The test OTP is 123456.'}</p>
          <div className="row"><input className="input grow" placeholder="98xxxxxxxx" value={phone} onChange={(e) => setPhone(e.target.value)} aria-label="Test phone" />
            <Button loading={t.isLoading} disabled={phone.length < 10} onClick={() => test({ purpose, phone })}>Send</Button></div>
          {acc.last_tested_at && <div className="small" style={{ marginTop: 8 }}>Last test {fmtDate(acc.last_tested_at, true)}: {acc.last_test_ok ? <Badge tone="green">worked</Badge> : <Badge tone="red">failed</Badge>} <span className="muted">(details in Message log)</span></div>}
        </Card>
        <Card title="Placeholders">
          {Object.entries(placeholders).filter(([k]) => (isOtp ? !['{{file_url}}', '{{file_name}}', '{{caption}}'].includes(k) : k !== '{{otp}}')).map(([k, d]) => (
            <div key={k} className="small" style={{ padding: '4px 0' }}><span className="kbd">{k}</span> {d}</div>
          ))}
        </Card>
      </div>
    </div>
  );
}

function Content({ templates, auto }) {
  const [save, s] = useSaveWaTemplatesMutation();
  const [t, setT] = useState(() => Object.fromEntries(templates.map((x) => [x.key, x.text])));
  const [a, setA] = useState(auto);
  const LABEL = { payment_link: '💳 Payment link', invoice: '🧾 Invoice (sent with the PDF)', admission: '🎓 Manual admission (free / no invoice)', swap: '🔁 Course swap done', swap_payment: '🔁 Course swap – pay the difference' };
  const AUTO = [['payment_link', 'Tick “Send on WhatsApp” by default when creating a payment link'], ['invoice', 'Send the invoice PDF automatically after every online payment'],
    ['admission', 'Send the welcome message after online payments (when there is no invoice)'], ['swap', 'Tell the student when a course swap is done']];
  return (
    <div className="grid g2" style={{ alignItems: 'start' }}>
      <Card title="Message content">
        {templates.map((x) => (
          <div key={x.key} style={{ marginBottom: 14 }}>
            <Field f={{ name: x.key, label: LABEL[x.key] || x.key, type: 'textarea', rows: 4 }} value={t[x.key]} onChange={(v) => setT({ ...t, [x.key]: v })} />
            <div className="row wrap small" style={{ marginTop: 4 }}>
              {x.variables.map((vv) => <button key={vv} type="button" className="chip" onClick={() => setT({ ...t, [x.key]: `${t[x.key] || ''}{${vv}}` })}>{`{${vv}}`}</button>)}
              <button type="button" className="btn ghost sm" onClick={() => setT({ ...t, [x.key]: x.default })}>Reset</button>
            </div>
          </div>
        ))}
      </Card>
      <Card title="Automatic sending" actions={<Can perm="whatsapp.manage"><Button variant="primary" loading={s.isLoading} onClick={() => save({ templates: t, auto: a })}>Save</Button></Can>}>
        {AUTO.map(([k, label]) => (
          <div key={k} className="row between" style={{ padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
            <span>{label}</span><Toggle on={!!a[k]} onChange={(v) => setA({ ...a, [k]: v })} label={label} />
          </div>
        ))}
        <p className="small muted">*Bold* and _italic_ work like in WhatsApp. Emojis and Malayalam are fine.</p>
      </Card>
    </div>
  );
}

function Log() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useWaMessagesQuery(f, { pollingInterval: 20000 });
  const [retry] = useRetryWaMutation();
  const [open, setOpen] = useState(null);
  const can = useCan();
  const sm = data?.meta?.summary || {};
  return (
    <>
      <div className="grid g4" style={{ marginBottom: 14 }}>
        <Stat label="Messages today" value={sm.today} />
        <Stat label="Delivered to provider" value={sm.sent_today} />
        <Stat label="Failed today" value={sm.failed_today} />
        <Stat label="Last 30 days" value={<span className="small">{Object.entries(sm.by_purpose || {}).map(([k, n]) => `${k.replace('_', ' ')} ${n}`).join(' · ') || '—'}</span>} />
      </div>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} placeholder="Phone number" filters={[
            { name: 'status', label: 'Status', options: ['sent', 'failed', 'queued'].map((x) => ({ value: x, label: x })) },
            { name: 'purpose', label: 'Type', options: ['payment_link', 'invoice', 'admission', 'swap', 'otp', 'manual', 'test'].map((x) => ({ value: x, label: x.replace('_', ' ') })) },
            { name: 'account', label: 'API', options: [{ value: 'share', label: 'Share API' }, { value: 'otp', label: 'OTP API' }] },
          ]} />
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={setOpen}
          columns={[
            { key: 'created_at', label: 'Time', render: (m) => <span className="nowrap">{fmtDate(m.created_at, true)}</span> },
            { key: 'to_phone', label: 'To' },
            { key: 'purpose', label: 'Type', render: (m) => <span>{m.type === 'document' ? '📄 ' : ''}{m.purpose.replace('_', ' ')}{m.order_no && <div className="small muted">{m.order_no}</div>}</span> },
            { key: 'body', label: 'Message', render: (m) => <span className="small">{(m.body || '').slice(0, 90)}</span> },
            { key: 'status', label: 'Status', render: (m) => <div><Badge>{m.status}</Badge>{m.error && <div className="small" style={{ color: 'var(--red)' }}>{m.error.slice(0, 60)}</div>}</div> },
            { key: 'sent_by', label: 'By', render: (m) => m.sent_by || 'system' },
            { key: 'x', label: '', render: (m) => m.status === 'failed' && m.account === 'share' && can('whatsapp.send') && <Button size="sm" onClick={(e) => { e.stopPropagation(); retry(m.id); }}>Retry</Button> },
          ]} />
      </Card>
      {open && (
        <Modal title={`Message #${open.id}`} onClose={() => setOpen(null)}>
          <table className="t"><tbody>
            {[['To', open.to_phone], ['API', open.account], ['Type', `${open.purpose} (${open.type})`], ['Status', open.status], ['HTTP', open.http_status], ['Attempts', open.attempts],
              ['File', open.file_url && <a key="f" href={open.file_url} target="_blank" rel="noreferrer">{open.file_name}</a>], ['Error', open.error], ['Sent at', fmtDate(open.sent_at, true)]]
              .map(([k, v]) => <tr key={k}><td className="muted">{k}</td><td>{v ?? '—'}</td></tr>)}
          </tbody></table>
          <h3 style={{ marginTop: 12 }}>Message</h3>
          <div className="card" style={{ whiteSpace: 'pre-wrap', background: 'var(--mint)', border: 0 }}>{open.body}</div>
          {open.response && <><h3 style={{ marginTop: 12 }}>Provider reply</h3><pre className="card small" style={{ whiteSpace: 'pre-wrap', overflow: 'auto', maxHeight: 200 }}>{open.response}</pre></>}
        </Modal>
      )}
    </>
  );
}

function SendBox() {
  const [send, s] = useSendWaMutation();
  const [v, setV] = useState({ phone: '', message: '' });
  return (
    <Card title="Send a WhatsApp message" className="col">
      <p className="small muted" style={{ marginTop: 0 }}>Uses the share API – for quick replies, reminders or follow-ups to one student.</p>
      <Field f={{ name: 'phone', label: 'Mobile number', required: true }} value={v.phone} onChange={(phone) => setV({ ...v, phone })} />
      <Field f={{ name: 'message', label: 'Message', type: 'textarea', rows: 5, required: true }} value={v.message} onChange={(message) => setV({ ...v, message })} />
      <div><Button variant="primary" loading={s.isLoading} disabled={!v.phone || !v.message} onClick={async () => { await send(v).unwrap(); setV({ phone: '', message: '' }); }}>Send</Button></div>
    </Card>
  );
}

export default function WhatsAppPage() {
  const can = useCan();
  const manage = can('whatsapp.manage');
  const { data, isLoading } = useWaSettingsQuery(undefined, { skip: !manage });
  const [tab, setTab] = useState(manage ? 'share' : 'log');
  if (manage && (isLoading || !data)) return <Spinner />;

  return (
    <>
      <PageHead title="WhatsApp" sub="Your WhatsApp API for payment links, invoice PDFs, admission messages and login OTP. URL and token are saved in the database." />
      <Tabs value={tab} onChange={setTab} tabs={[
        manage && { key: 'share', label: `🔗 Share API ${data?.share.ready ? '✅' : ''}` },
        manage && { key: 'otp', label: `🔐 OTP ${data?.otp.ready ? '(ON)' : '(OFF)'}` },
        manage && { key: 'content', label: '✍️ Message content' },
        can('whatsapp.view') && { key: 'log', label: '📜 Message log' },
        can('whatsapp.send') && { key: 'send', label: '✉️ Send message' },
      ]} />
      {tab === 'share' && data && <Connection purpose="share" acc={data.share} presets={data.presets} placeholders={data.placeholders} />}
      {tab === 'otp' && data && <Connection purpose="otp" acc={data.otp} presets={data.presets} placeholders={data.placeholders} />}
      {tab === 'content' && data && <Content templates={data.templates} auto={data.auto} />}
      {tab === 'log' && <Log />}
      {tab === 'send' && <SendBox />}
    </>
  );
}
