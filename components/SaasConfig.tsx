import React, { useState, useEffect } from 'react';

const API_BASE = '';

export const SaasConfig: React.FC = () => {
  const [saasCompanyName, setSaasCompanyName] = useState('');
  const [creditPriceAvulso, setCreditPriceAvulso] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/platform-config.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.data) {
        const d = data.data;
        setSaasCompanyName(d.saasCompanyName ?? '');
        setCreditPriceAvulso(d.creditPriceAvulso != null && d.creditPriceAvulso > 0 ? String(d.creditPriceAvulso) : '');
      } else {
        setError(data.error || 'Erro ao carregar configurações.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    const avulso = creditPriceAvulso ? parseFloat(creditPriceAvulso.replace(',', '.')) : 0;
    if (avulso < 0) {
      setError('O valor avulso não pode ser negativo.');
      setSaving(false);
      return;
    }
    try {
      const res = await fetch(`${API_BASE}/api/platform-config.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          saasCompanyName: saasCompanyName.trim(),
          creditPriceAvulso: avulso,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setSuccess(data.message || 'Configurações salvas.');
      } else {
        setError(data.error || 'Erro ao salvar.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-2xl mx-auto py-24 text-center">
        <span className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" />
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <h3 className="text-2xl font-black text-slate-900 mb-2">Configuração da empresa SaaS</h3>
      <p className="text-sm text-slate-500 mb-8">Configure o nome da empresa e o valor por crédito avulso (usado em Solicitar créditos).</p>

      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">
          {error}
        </div>
      )}
      {success && (
        <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium">
          {success}
        </div>
      )}

      <div className="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome da empresa SaaS</label>
            <input
              type="text"
              value={saasCompanyName}
              onChange={(e) => setSaasCompanyName(e.target.value)}
              className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
              placeholder="Ex: MapsProspector Pro"
            />
          </div>
          <div>
            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Valor avulso por crédito (R$)</label>
            <input
              type="text"
              inputMode="decimal"
              value={creditPriceAvulso}
              onChange={(e) => setCreditPriceAvulso(e.target.value.replace(/[^\d,.]/g, ''))}
              className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
              placeholder="Ex: 2,00"
            />
            <p className="text-[10px] text-slate-400 mt-1">Este valor é usado em Solicitar créditos para calcular o total a pagar (quantidade × valor por crédito).</p>
          </div>
          <button
            type="submit"
            disabled={saving}
            className="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-70"
          >
            {saving ? 'Salvando...' : 'Salvar configurações'}
          </button>
        </form>
      </div>
    </div>
  );
};
