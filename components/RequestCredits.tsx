import React, { useState, useEffect } from 'react';

const API_BASE = '';

interface CreditRequestRow {
  id: string;
  tenantId: string;
  tokensRequested: number;
  status: string;
  createdAt: string;
  reviewedAt?: string;
}

export const RequestCredits: React.FC = () => {
  const [list, setList] = useState<CreditRequestRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [amount, setAmount] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadList = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/credit-requests.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.data?.items) {
        setList(data.data.items as CreditRequestRow[]);
      } else {
        setError(data.error || 'Erro ao carregar solicitações.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadList();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const num = parseInt(amount, 10);
    if (isNaN(num) || num < 1 || num > 100000) {
      setError('Informe uma quantidade entre 1 e 100.000.');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/credit-requests.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ tokensRequested: num }),
      });
      const data = await res.json();
      if (data.success) {
        setAmount('');
        loadList();
      } else {
        setError(data.error || 'Erro ao enviar solicitação.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setSubmitting(false);
    }
  };

  const pending = list.filter((r) => r.status === 'pending');

  return (
    <div className="max-w-2xl mx-auto">
      <h3 className="text-2xl font-black text-slate-900 mb-6">Solicitar créditos</h3>
      <p className="text-sm text-slate-500 mb-8">Solicite créditos (tokens) adicionais para sua empresa. O administrador da plataforma analisará e poderá aprovar ou recusar.</p>

      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">
          {error}
        </div>
      )}

      <div className="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm mb-10">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Quantidade de créditos (tokens)</label>
            <input
              type="number"
              min={1}
              max={100000}
              value={amount}
              onChange={(e) => setAmount(e.target.value.replace(/\D/g, '').slice(0, 6))}
              className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
              placeholder="Ex: 50"
              required
            />
            <p className="text-[10px] text-slate-400 mt-1">Cada crédito = 1 busca no período atual.</p>
          </div>
          <button
            type="submit"
            disabled={submitting}
            className="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-70"
          >
            {submitting ? 'Enviando...' : 'Enviar solicitação'}
          </button>
        </form>
      </div>

      <h4 className="text-lg font-black text-slate-900 mb-4">Minhas solicitações</h4>
      {loading ? (
        <div className="py-12 text-center">
          <span className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" />
        </div>
      ) : list.length === 0 ? (
        <div className="py-12 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
          <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação</p>
        </div>
      ) : (
        <div className="space-y-4">
          {list.map((r) => (
            <div
              key={r.id}
              className="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between"
            >
              <div>
                <p className="font-extrabold text-slate-900">{r.tokensRequested} créditos</p>
                <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  {r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : ''} ·{' '}
                  <span className={r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600'}>
                    {r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado'}
                  </span>
                </p>
              </div>
              {r.status === 'pending' && (
                <span className="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold">Aguardando</span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
