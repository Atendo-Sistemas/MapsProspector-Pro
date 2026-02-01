
import React, { useState, useEffect } from 'react';
import { TenantRow, PlanRow } from '../types';

const API_BASE = '';

interface CompaniesProps {
  /** Quando muda, a lista é recarregada (ex.: ao abrir a aba Empresas). */
  refreshKey?: number;
}

export const Companies: React.FC<CompaniesProps> = ({ refreshKey = 0 }) => {
  const [list, setList] = useState<TenantRow[]>([]);
  const [plans, setPlans] = useState<PlanRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modal, setModal] = useState<'none' | 'create' | 'edit'>('none');
  const [form, setForm] = useState({ id: '', name: '', slug: '', planId: '1', status: 'active' });
  const [saving, setSaving] = useState(false);

  const loadList = async () => {
    setLoading(true);
    setError(null);
    try {
      const [tenantsRes, plansRes] = await Promise.all([
        fetch(`${API_BASE}/api/tenants.php`, { credentials: 'include' }),
        fetch(`${API_BASE}/api/plans.php`, { credentials: 'include' }),
      ]);
      const tenantsData = await tenantsRes.json();
      const plansData = await plansRes.json();
      if (tenantsData.success && tenantsData.data) {
        const items = Array.isArray(tenantsData.data.items) ? tenantsData.data.items : (Array.isArray(tenantsData.data) ? tenantsData.data : []);
        setList(items as TenantRow[]);
      } else {
        setError(tenantsData.error || 'Erro ao carregar empresas.');
      }
      if (plansData.success && plansData.data) {
        const planItems = Array.isArray(plansData.data.items) ? plansData.data.items : (Array.isArray(plansData.data) ? plansData.data : []);
        setPlans(planItems as PlanRow[]);
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

  const openCreate = () => {
    setForm({ id: '', name: '', slug: '', planId: plans.length ? plans[0].id : '1', status: 'active' });
    setModal('create');
  };

  const openEdit = (t: TenantRow) => {
    setForm({
      id: t.id,
      name: t.name,
      slug: t.slug,
      planId: t.planId ?? t.plan ?? '1',
      status: t.status || 'active',
    });
    setModal('edit');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      if (modal === 'create') {
        const res = await fetch(`${API_BASE}/api/tenants.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            name: form.name.trim(),
            slug: form.slug.trim() || undefined,
            planId: form.planId,
            status: form.status,
          }),
        });
        const data = await res.json();
        if (data.success) {
          setModal('none');
          loadList();
        } else {
          setError(data.error || 'Erro ao criar empresa.');
        }
      } else {
        const res = await fetch(`${API_BASE}/api/tenants.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            id: form.id,
            name: form.name.trim(),
            slug: form.slug.trim(),
            planId: form.planId,
            status: form.status,
          }),
        });
        const data = await res.json();
        if (data.success) {
          setModal('none');
          loadList();
        } else {
          setError(data.error || 'Erro ao atualizar empresa.');
        }
      }
    } catch {
      setError('Erro de conexão.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: string, name: string) => {
    if (id === '1') {
      alert('Não é permitido excluir a empresa padrão.');
      return;
    }
    if (!confirm(`Excluir a empresa "${name}"? Os usuários serão movidos para a empresa padrão.`)) return;
    try {
      const res = await fetch(`${API_BASE}/api/tenants.php?id=${id}`, {
        method: 'DELETE',
        credentials: 'include',
      });
      const data = await res.json();
      if (data.success) {
        loadList();
      } else {
        alert(data.error || 'Erro ao excluir.');
      }
    } catch {
      alert('Erro de conexão.');
    }
  };

  const slugFromName = (name: string) =>
    name
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^\-|\-$/g, '');

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex justify-between items-center mb-10">
        <h3 className="text-2xl font-black text-slate-900">Empresas cadastradas</h3>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => loadList()}
            disabled={loading}
            className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2 disabled:opacity-70"
            title="Atualizar lista (inclui empresas recém-cadastradas)"
          >
            <svg className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            Atualizar lista
          </button>
          <button
            onClick={openCreate}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2"
          >
            <span>Nova empresa</span>
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
          </button>
        </div>
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
      ) : list.length === 0 ? (
        <div className="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
          <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhuma empresa cadastrada</p>
          <button onClick={openCreate} className="mt-4 text-blue-600 font-bold text-sm">Cadastrar primeira empresa</button>
        </div>
      ) : (
        <div className="space-y-4">
          {list.map((t) => (
            <div
              key={t.id}
              className="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between group hover:border-blue-200 transition-all"
            >
              <div className="flex items-center gap-6">
                <div className="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-xl font-black text-slate-600">
                  {t.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <h4 className="font-extrabold text-slate-900 text-lg">{t.name}</h4>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    {t.slug} · Plano: {t.plan}{t.planTokenLimit != null ? ` (${t.planTokenLimit === 0 ? 'ilimitado' : t.planTokenLimit + ' tokens)'}` : ''} · {t.usersCount} usuário(s) · {t.status === 'active' ? 'Ativa' : 'Suspensa'}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => openEdit(t)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs"
                >
                  Editar
                </button>
                {t.id !== '1' && (
                  <button
                    onClick={() => handleDelete(t.id, t.name)}
                    className="text-red-500 hover:bg-red-50 font-bold px-4 py-2 rounded-xl text-xs"
                  >
                    Excluir
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {modal !== 'none' && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4" onClick={() => !saving && setModal('none')}>
          <div
            className="bg-white rounded-[2rem] shadow-2xl p-8 max-w-md w-full"
            onClick={(e) => e.stopPropagation()}
          >
            <h4 className="text-xl font-black text-slate-900 mb-6">
              {modal === 'create' ? 'Nova empresa' : 'Editar empresa'}
            </h4>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => {
                    setForm((f) => ({
                      ...f,
                      name: e.target.value,
                      slug: modal === 'create' ? slugFromName(e.target.value) : f.slug,
                    }));
                  }}
                  className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  required
                  placeholder="Razão social ou nome fantasia"
                />
              </div>
              <div>
                <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Identificador (slug)</label>
                <input
                  type="text"
                  value={form.slug}
                  onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
                  className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  placeholder="minha-empresa"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Plano</label>
                  <select
                    value={form.planId}
                    onChange={(e) => setForm((f) => ({ ...f, planId: e.target.value }))}
                    className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  >
                    {plans.filter((p) => p.status === 'active').map((p) => (
                      <option key={p.id} value={p.id}>{p.name} ({p.tokenLimit === 0 ? 'ilimitado' : p.tokenLimit + ' tokens'})</option>
                    ))}
                    {plans.length === 0 && <option value="1">Básico</option>}
                  </select>
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Status</label>
                  <select
                    value={form.status}
                    onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
                    className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  >
                    <option value="active">Ativa</option>
                    <option value="suspended">Suspensa</option>
                  </select>
                </div>
              </div>
              {error && <p className="text-red-600 text-sm font-medium">{error}</p>}
              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => !saving && setModal('none')}
                  className="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-70"
                >
                  {saving ? 'Salvando...' : modal === 'create' ? 'Criar' : 'Salvar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
