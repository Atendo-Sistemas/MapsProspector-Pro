import React, { useState, useEffect } from 'react';

const API_BASE = '';

interface CreditRequestRow {
  id: string;
  tenantId: string;
  tenantName: string | null;
  requestedByUserId: string;
  requestedByName: string | null;
  requestedByEmail: string | null;
  tokensRequested: number;
  status: string;
  createdAt: string;
  reviewedAt?: string;
}

export const CreditsAdmin: React.FC<{ refreshKey?: number }> = ({ refreshKey = 0 }) => {
  const [list, setList] = useState<CreditRequestRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actingId, setActingId] = useState<string | null>(null);

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
  }, [refreshKey]);

  const handleReview = async (id: string, status: 'approved' | 'rejected') => {
    setActingId(id);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/credit-requests.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id, status }),
      });
      const data = await res.json();
      if (data.success) {
        loadList();
      } else {
        setError(data.error || 'Erro ao processar.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setActingId(null);
    }
  };

  const pending = list.filter((r) => r.status === 'pending');

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex justify-between items-center mb-10">
        <h3 className="text-2xl font-black text-slate-900">Solicitações de créditos</h3>
        <button
          type="button"
          onClick={() => loadList()}
          disabled={loading}
          className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2 disabled:opacity-70"
        >
          <svg className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          Atualizar
        </button>
      </div>

      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">
          {error}
        </div>
      )}

      {loading ? (
        <div className="py-24 text-center">
          <span className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" />
        </div>
      ) : pending.length === 0 && list.length === 0 ? (
        <div className="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
          <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação de créditos</p>
        </div>
      ) : (
        <div className="space-y-4">
          {list.map((r) => (
            <div
              key={r.id}
              className="bg-white p-6 rounded-[2rem] border border-slate-200 flex flex-wrap items-center justify-between gap-4"
            >
              <div className="flex items-center gap-6">
                <div className="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-xl font-black text-slate-600">
                  {(r.tenantName || 'E').charAt(0).toUpperCase()}
                </div>
                <div>
                  <h4 className="font-extrabold text-slate-900 text-lg">{r.tenantName || 'Empresa'}</h4>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    {r.requestedByName || r.requestedByEmail || '—'} · {r.tokensRequested} créditos ·{' '}
                    {r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : ''}
                  </p>
                  <p className={`text-xs font-bold mt-1 ${r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600'}`}>
                    {r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado'}
                    {r.reviewedAt && ` · ${new Date(r.reviewedAt).toLocaleString('pt-BR')}`}
                  </p>
                </div>
              </div>
              {r.status === 'pending' && (
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => handleReview(r.id, 'approved')}
                    disabled={actingId !== null}
                    className="bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-4 py-2 rounded-xl text-xs disabled:opacity-70"
                  >
                    {actingId === r.id ? '...' : 'Aceitar'}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleReview(r.id, 'rejected')}
                    disabled={actingId !== null}
                    className="bg-red-100 text-red-700 hover:bg-red-200 font-bold px-4 py-2 rounded-xl text-xs disabled:opacity-70"
                  >
                    {actingId === r.id ? '...' : 'Recusar'}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
