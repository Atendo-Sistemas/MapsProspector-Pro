
import React, { useState, useEffect } from 'react';
import { PlanRow } from '../types';

const API_BASE = '';

interface PlansProps {
  refreshKey?: number;
}

export const Plans: React.FC<PlansProps> = ({ refreshKey = 0 }) => {
  const [list, setList] = useState<PlanRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modal, setModal] = useState<'none' | 'create' | 'edit'>('none');
  const [form, setForm] = useState({
    id: '',
    name: '',
    slug: '',
    tokenLimit: 100,
    priceMonthly: 0,
    period: 'monthly' as 'monthly' | 'yearly',
    status: 'active' as 'active' | 'inactive',
  });
  const [saving, setSaving] = useState(false);

  const loadList = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/api/plans.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && data.data) {
        const items = Array.isArray(data.data.items) ? data.data.items : (Array.isArray(data.data) ? data.data : []);
        setList(items as PlanRow[]);
      } else {
        setError(data.error || 'Erro ao carregar planos.');
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
    setForm({
      id: '',
      name: '',
      slug: '',
      tokenLimit: 100,
      priceMonthly: 0,
      period: 'monthly',
      status: 'active',
    });
    setModal('create');
  };

  const openEdit = (p: PlanRow) => {
    setForm({
      id: p.id,
      name: p.name,
      slug: p.slug,
      tokenLimit: p.tokenLimit ?? 100,
      priceMonthly: p.priceMonthly ?? 0,
      period: (p.period === 'yearly' ? 'yearly' : 'monthly') as 'monthly' | 'yearly',
      status: (p.status === 'inactive' ? 'inactive' : 'active') as 'active' | 'inactive',
    });
    setModal('edit');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      if (modal === 'create') {
        const res = await fetch(`${API_BASE}/api/plans.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            name: form.name.trim(),
            slug: form.slug.trim() || undefined,
            tokenLimit: form.tokenLimit,
            priceMonthly: form.priceMonthly,
            period: form.period,
            status: form.status,
          }),
        });
        const data = await res.json();
        if (data.success) {
          setModal('none');
          loadList();
        } else {
          setError(data.error || 'Erro ao criar plano.');
        }
      } else {
        const res = await fetch(`${API_BASE}/api/plans.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            id: form.id,
            name: form.name.trim(),
            slug: form.slug.trim(),
            tokenLimit: form.tokenLimit,
            priceMonthly: form.priceMonthly,
            period: form.period,
            status: form.status,
          }),
        });
        const data = await res.json();
        if (data.success) {
          setModal('none');
          loadList();
        } else {
          setError(data.error || 'Erro ao atualizar plano.');
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
      alert('Não é permitido excluir o plano padrão (Básico).');
      return;
    }
    if (!confirm(`Excluir o plano "${name}"? Nenhuma empresa pode estar vinculada a ele.`)) return;
    try {
      const res = await fetch(`${API_BASE}/api/plans.php?id=${id}`, {
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
        <h3 className="text-2xl font-black text-slate-900">Planos e limite de tokens</h3>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => loadList()}
            disabled={loading}
            className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2 disabled:opacity-70"
            title="Atualizar lista"
          >
            <svg className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            Atualizar lista
          </button>
          <button
            onClick={openCreate}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2"
          >
            <span>Novo plano</span>
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
          <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhum plano cadastrado</p>
          <button onClick={openCreate} className="mt-4 text-blue-600 font-bold text-sm">Cadastrar primeiro plano</button>
        </div>
      ) : (
        <div className="space-y-4">
          {list.map((p) => (
            <div
              key={p.id}
              className="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between group hover:border-blue-200 transition-all"
            >
              <div className="flex items-center gap-6">
                <div className="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">
                  {p.tokenLimit === 0 ? '∞' : p.tokenLimit}
                </div>
                <div>
                  <h4 className="font-extrabold text-slate-900 text-lg">{p.name}</h4>
                  <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    {p.slug} · {p.tokenLimit === 0 ? 'Ilimitado' : `${p.tokenLimit} tokens/${p.period === 'yearly' ? 'ano' : 'mês'}`}
                    {(p.priceMonthly != null && p.priceMonthly > 0) ? ` · R$ ${Number(p.priceMonthly).toFixed(2).replace('.', ',')}/mês` : ''}
                    {' · '}{p.tenantsCount ?? 0} empresa(s) · {p.status === 'active' ? 'Ativo' : 'Inativo'}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => openEdit(p)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs"
                >
                  Editar
                </button>
                {p.id !== '1' && (
                  <button
                    onClick={() => handleDelete(p.id, p.name)}
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
              {modal === 'create' ? 'Novo plano' : 'Editar plano'}
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
                  placeholder="Ex: Básico, Pro, Enterprise"
                />
              </div>
              <div>
                <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Identificador (slug)</label>
                <input
                  type="text"
                  value={form.slug}
                  onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
                  className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  placeholder="basic, pro, enterprise"
                />
              </div>
              <div>
                <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Limite de tokens por período</label>
                <input
                  type="number"
                  min={0}
                  value={form.tokenLimit}
                  onChange={(e) => setForm((f) => ({ ...f, tokenLimit: Math.max(0, parseInt(e.target.value, 10) || 0) }))}
                  className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  placeholder="100"
                />
                <p className="text-[10px] text-slate-400 mt-1">0 = ilimitado. Cada página de 20 resultados consome 1 token.</p>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Período</label>
                  <select
                    value={form.period}
                    onChange={(e) => setForm((f) => ({ ...f, period: e.target.value as 'monthly' | 'yearly' }))}
                    className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  >
                    <option value="monthly">Mensal</option>
                    <option value="yearly">Anual</option>
                  </select>
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Status</label>
                  <select
                    value={form.status}
                    onChange={(e) => setForm((f) => ({ ...f, status: e.target.value as 'active' | 'inactive' }))}
                    className="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  >
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
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
                  {saving ? 'Salvando...' : modal === 'create' ? 'Criar plano' : 'Salvar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
