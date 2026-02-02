
import React, { useState } from 'react';
import { AppUser, AppTenant, TokenUsage } from '../types';

const API_BASE = '';

interface LoginProps {
  onLogin: (data: { user: AppUser; tenant: AppTenant; tokenUsage?: TokenUsage }) => void;
}

export const Login: React.FC<LoginProps> = ({ onLogin }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showRegister, setShowRegister] = useState(false);
  const [registerSuccess, setRegisterSuccess] = useState<string | null>(null);

  const [regCompany, setRegCompany] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regName, setRegName] = useState('');
  const [regPassword, setRegPassword] = useState('');
  const [regPasswordConfirm, setRegPasswordConfirm] = useState('');
  const [regLoading, setRegLoading] = useState(false);
  const [regError, setRegError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setRegisterSuccess(null);
    const trimmed = email.trim().toLowerCase();
    if (!trimmed || !trimmed.includes('@')) {
      setError('Por favor, insira um e-mail válido.');
      return;
    }
    if (!password) {
      setError('Por favor, insira sua senha.');
      return;
    }
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', email: trimmed, password }),
        credentials: 'include',
      });
      const data = await res.json();
      if (data.success && data.data?.user && data.data?.tenant) {
        onLogin({
          user: data.data.user as AppUser,
          tenant: data.data.tenant as AppTenant,
          tokenUsage: data.data.tokenUsage,
        });
      } else {
        setError(data.error || 'Falha no login. Tente novamente.');
      }
    } catch (err) {
      setError('Erro de conexão. Verifique o servidor.');
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setRegError(null);
    const company = regCompany.trim();
    const mail = regEmail.trim().toLowerCase();
    if (!company) {
      setRegError('Nome da empresa é obrigatório.');
      return;
    }
    if (!mail || !mail.includes('@')) {
      setRegError('E-mail do administrador é obrigatório e deve ser válido.');
      return;
    }
    if (regPassword.length < 6) {
      setRegError('A senha deve ter no mínimo 6 caracteres.');
      return;
    }
    if (regPassword !== regPasswordConfirm) {
      setRegError('As senhas não coincidem.');
      return;
    }
    setRegLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/register.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          companyName: company,
          adminEmail: mail,
          adminName: regName.trim() || undefined,
          adminPassword: regPassword,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setRegisterSuccess(data.message || 'Empresa cadastrada. Faça login com seu e-mail e senha.');
        setEmail(mail);
        setShowRegister(false);
        setRegCompany('');
        setRegEmail('');
        setRegName('');
        setRegPassword('');
        setRegPasswordConfirm('');
      } else {
        setRegError(data.error || 'Erro ao cadastrar. Tente novamente.');
      }
    } catch {
      setRegError('Erro de conexão. Verifique o servidor.');
    } finally {
      setRegLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0F172A] px-4 overflow-hidden relative">
      <div className="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
        <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600 rounded-full blur-[120px]"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-900 rounded-full blur-[120px]"></div>
      </div>

      <div className="max-w-md w-full z-10">
        <div className="bg-white rounded-[2.5rem] shadow-2xl p-12 border border-slate-200 text-center">
          <div className="mb-10">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl mb-6 shadow-xl shadow-blue-200 transform rotate-3 hover:rotate-0 transition-all duration-500">
              <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <h1 className="text-3xl font-black text-slate-900 tracking-tight mb-2">Atendo Maps</h1>
            <p className="text-slate-500 font-medium text-sm">Ferramenta de Prospecção Inteligente</p>
          </div>

          {registerSuccess && (
            <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium">
              {registerSuccess}
            </div>
          )}

          {!showRegister ? (
            <>
              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-left space-y-4">
                  <div>
                    <label className="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1">E-mail</label>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="seu@email.com"
                      className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                      autoComplete="email"
                      disabled={loading}
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1">Senha</label>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="••••••••"
                      className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                      autoComplete="current-password"
                      disabled={loading}
                    />
                  </div>
                  {error && (
                    <p className="mt-2 text-xs font-bold text-red-500">{error}</p>
                  )}
                  <p className="text-[11px] text-slate-500 font-medium leading-relaxed mt-3">
                    Use o e-mail e a senha cadastrados na plataforma. Após entrar, configure a integração em <span className="font-bold text-slate-700">Configurações</span>.
                  </p>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-3 text-sm tracking-wide uppercase"
                >
                  {loading ? (
                    <>
                      <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                      Entrando...
                    </>
                  ) : (
                    <>
                      Entrar
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </>
                  )}
                </button>
              </form>

              <div className="mt-8 pt-6 border-t border-slate-100">
                <p className="text-[11px] text-slate-500 font-medium mb-2">Ainda não tem empresa cadastrada?</p>
                <button
                  type="button"
                  onClick={() => { setShowRegister(true); setError(null); setRegisterSuccess(null); }}
                  className="text-blue-600 font-bold text-sm hover:underline"
                >
                  Cadastrar minha empresa
                </button>
              </div>
            </>
          ) : (
            <>
              <form onSubmit={handleRegister} className="space-y-4 text-left">
                <h3 className="text-lg font-black text-slate-900 mb-4 text-center">Nova empresa</h3>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome da empresa</label>
                  <input
                    type="text"
                    value={regCompany}
                    onChange={(e) => setRegCompany(e.target.value)}
                    placeholder="Razão social ou nome fantasia"
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                    required
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">E-mail do administrador</label>
                  <input
                    type="email"
                    value={regEmail}
                    onChange={(e) => setRegEmail(e.target.value)}
                    placeholder="admin@empresa.com"
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                    required
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Seu nome (opcional)</label>
                  <input
                    type="text"
                    value={regName}
                    onChange={(e) => setRegName(e.target.value)}
                    placeholder="Nome do responsável"
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Senha (mín. 6 caracteres)</label>
                  <input
                    type="password"
                    value={regPassword}
                    onChange={(e) => setRegPassword(e.target.value)}
                    placeholder="••••••••"
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                    autoComplete="new-password"
                    minLength={6}
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-500 uppercase mb-1">Confirmar senha</label>
                  <input
                    type="password"
                    value={regPasswordConfirm}
                    onChange={(e) => setRegPasswordConfirm(e.target.value)}
                    placeholder="••••••••"
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                    autoComplete="new-password"
                    minLength={6}
                  />
                </div>
                {regError && <p className="text-xs font-bold text-red-500">{regError}</p>}
                <div className="flex gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => { setShowRegister(false); setRegError(null); setRegCompany(''); setRegEmail(''); setRegName(''); setRegPassword(''); setRegPasswordConfirm(''); }}
                    className="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50 text-sm"
                  >
                    Voltar
                  </button>
                  <button
                    type="submit"
                    disabled={regLoading}
                    className="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-70 text-sm"
                  >
                    {regLoading ? 'Cadastrando...' : 'Cadastrar'}
                  </button>
                </div>
              </form>
            </>
          )}

          <div className="mt-10 pt-6 border-t border-slate-100">
            <p className="text-center text-[10px] text-slate-400 font-semibold tracking-wider uppercase">
              Atendo Tecnologia © 2024
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
