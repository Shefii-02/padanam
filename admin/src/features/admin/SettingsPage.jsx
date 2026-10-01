import { useSettingsQuery, useSaveSettingsMutation, useVersionsQuery, useSaveVersionMutation } from './api';
import { Can, Card, PageHead, Spinner } from '../../components/ui';
import { SchemaForm } from '../../components/Form';

export default function SettingsPage() {
  const { data: s, isLoading } = useSettingsQuery();
  const [save, ss] = useSaveSettingsMutation();
  const { data: versions = [] } = useVersionsQuery();
  const [saveVersion] = useSaveVersionMutation();

  return (
    <>
      <PageHead title="Settings" />
      <div className="grid g2" style={{ alignItems: 'start' }}>
        <Can perm="settings.manage">
          <Card title="Invoice & alerts">
            {isLoading || !s ? <Spinner /> : (
              <SchemaForm loading={ss.isLoading} initial={{
                seller_name: s['invoice.seller']?.name, gstin: s['invoice.seller']?.gstin, address: s['invoice.seller']?.address, state_code: s['invoice.seller']?.state_code,
                prefix: s['invoice.prefix'], gst: s['invoice.gst_percent'], incl: s['invoice.prices_include_gst'], alert: s['live.alert_minutes_before'],
              }} fields={[
                { name: 'seller_name', label: 'Business name on invoices', required: true, cols: 2 }, { name: 'gstin', label: 'GSTIN' }, { name: 'state_code', label: 'State code', hint: 'Kerala = 32' },
                { name: 'address', label: 'Address', type: 'textarea', cols: 2 }, { name: 'prefix', label: 'Invoice prefix' }, { name: 'gst', label: 'GST %', type: 'number' },
                { name: 'incl', label: 'Course prices include GST', type: 'toggle' }, { name: 'alert', label: 'Class alert – minutes before', type: 'number', min: 1 },
              ]} onSubmit={(v) => save({
                'invoice.seller': { name: v.seller_name, gstin: v.gstin || null, address: v.address, state_code: v.state_code || '32' },
                'invoice.prefix': v.prefix, 'invoice.gst_percent': Number(v.gst), 'invoice.prices_include_gst': !!v.incl, 'live.alert_minutes_before': Number(v.alert),
              })} />
            )}
          </Card>
        </Can>
        <Can perm="app_versions.manage">
          <Card title="App versions">
            <p className="small muted" style={{ marginTop: 0 }}>Builds below the minimum must update (full-screen). Builds below the latest see a soft “update available”.</p>
            {versions.filter((v) => ['android', 'ios'].includes(v.platform)).map((v) => (
              <div key={v.platform} className="card" style={{ marginBottom: 10 }}>
                <h3>{v.platform === 'android' ? '🤖 Android' : '🍎 iOS'}</h3>
                <SchemaForm initial={v} submitText="Save" fields={[
                  { name: 'latest_version', label: 'Latest version', required: true }, { name: 'latest_build', label: 'Latest build', type: 'number', required: true },
                  { name: 'min_supported_build', label: 'Minimum build', type: 'number', required: true }, { name: 'store_url', label: 'Store link' },
                  { name: 'notes', label: "What's new", type: 'textarea', cols: 2 },
                ]} onSubmit={(x) => saveVersion({ platform: v.platform, latest_version: x.latest_version, latest_build: Number(x.latest_build), min_supported_build: Number(x.min_supported_build), store_url: x.store_url || null, notes: x.notes })} />
              </div>
            ))}
          </Card>
        </Can>
      </div>
    </>
  );
}
