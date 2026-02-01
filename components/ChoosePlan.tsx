import React, { useState, useEffect } from 'react';
import { PlanPublicRow, PlanRequestRow } from '../types';

const API_BASE = '';

interface ChoosePlanProps {
  currentPlanName?: string;
  currentPlanId?: string;
  onPlanRequestSent?: () => void;
}

export const ChoosePlan: React.FC<ChoosePlanProps> = ({
  currentPlanName,
  currentPlanId,
  onPlanRequestSent,
}) => {
  const [plans, setPlans] = useState<PlanPublicRow[]>([]);
  const [requests, setRequests] = useState<PlanRequestRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sendingPlanId, setSendingPlanId] = useState<string | null>(null);

  const loadPlans = async () => {
    try {
      const res = await fetch(`${API_BASE}/api/plans-public.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.data?.items) {
        setPlans(data.data.items as PlanPublicRow[]);
      }
    } catch {
      setError('Erro ao carregar planos.');
    }
  };

  const loadRequests = async () => {
    try {
      const res = await fetch(`${API_BASE}/api/plan-requests.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.data?.items) {
        setRequests(data.data.items as PlanRequestRow[]);
      }
    } catch {
      // Ignora; cliente pode não ter solicitações
    }
  };

  useEffect(() => {
    setLoading(true);
    setError(null);
    Promise.all([loadPlans(), loadRequests()]).finally(() => setLoading(false));
  }, []);

  const pendingRequest = requests.find((r) => r.status === 'pending');
  const hasPending = !!pendingRequest;

  const handleRequestPlan = async (planId: string) => {
    setSendingPlanId(planId);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/plan-requests.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ planId }),
      });
      const data = await res.json();
      if (data.success) {
        await loadRequests();
        onPlanRequestSent?.();
      } else {
        setError(data.error || 'Erro ao enviar solicitação.');
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setSendingPlanId(null);
    }
  };

  const formatPrice = (v: number) =>
    v > 0 ? `R$ ${Number(v).toFixed(2).replace('.', ',')}` : '—';

  if (loading) {
    return (
      <div className="max-w-4xl mx-auto py-24 text-center">
        <span className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto">
      <h3 className="text-2xl font-black text-slate-900 mb-2">Meu plano</h3>
      <p className="text-sm text-slate-500 mb-8">
        Escolha um plano para sua empresa. Após solicitar, o administrador confirmará e seu plano será atualizado.
      </p>

      {currentPlanName && (
        <div className="mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-200">
          <p className="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Plano atual</p>
          <p className="text-lg font-bold text-slate-900">{currentPlanName}</p>
        </div>
      )}

      {pendingRequest && (
        <div className="mb-8 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">
          <strong>Solicitação pendente:</strong> {pendingRequest.planName ?? 'Plano'} — aguardando confirmação do administrador.
        </div>
      )}

      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
        {plans.map((p) => {
          const isCurrent = currentPlanId === p.id;
          const canRequest = !hasPending && !isCurrent;
          return (
            <div
              key={p.id}
              className={`bg-white p-6 rounded-[2rem] border-2 transition-all ${
                isCurrent ? 'border-blue-400 bg-blue-50/50' : 'border-slate-200 hover:border-blue-200'
              }`}
            >
              <div className="flex items-center gap-4 mb-4">
                <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">
                  {p.tokenLimit >= 1000 ? `${p.tokenLimit / 1000}K` : p.tokenLimit}
                </div>
                <div>
                  <h4 className="font-extrabold text-slate-900 text-lg">{p.name}</h4>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    {p.slug === 'trial'
                      ? `${p.tokenLimit.toLocaleString('pt-BR')} créditos grátis`
                      : `${p.tokenLimit.toLocaleString('pt-BR')} tokens · ${p.period === 'yearly' ? 'ano' : 'mês'}`}
                  </p>
                </div>
              </div>
              <p className="text-2xl font-black text-slate-900 mb-4">
                {p.slug === 'trial' ? (
                  <span className="text-emerald-600">Grátis</span>
                ) : (
                  formatPrice(p.priceMonthly)
                )}
                <span className="text-sm font-bold text-slate-400">/mês</span>
              </p>
              {isCurrent ? (
                <p className="text-sm font-bold text-blue-600">Plano atual</p>
              ) : canRequest ? (
                <button
                  type="button"
                  onClick={() => handleRequestPlan(p.id)}
                  disabled={sendingPlanId !== null}
                  className="w-full py-3 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 disabled:opacity-70"
                >
                  {sendingPlanId === p.id ? 'Enviando...' : 'Solicitar este plano'}
                </button>
              ) : (
                <p className="text-sm text-slate-400">Aguarde a confirmação da solicitação em andamento.</p>
              )}
            </div>
          );
        })}
      </div>

      {requests.length > 0 && (
        <div>
          <h4 className="text-lg font-black text-slate-900 mb-4">Histórico de solicitações</h4>
          <div className="space-y-3">
            {requests.map((r) => (
              <div
                key={r.id}
                className="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between"
              >
                <span className="font-bold text-slate-800">{r.planName ?? 'Plano'}</span>
                <span className={`text-xs font-bold ${
                  r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600'
                }`}>
                  {r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Confirmado' : 'Recusado'}
                  {r.reviewedAt && ` · ${new Date(r.reviewedAt).toLocaleString('pt-BR')}`}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {plans.length === 0 && !error && (
        <div className="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
          <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhum plano disponível</p>
        </div>
      )}
    </div>
  );
};
