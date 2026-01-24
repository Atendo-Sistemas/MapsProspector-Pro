
import React, { useState } from 'react';
import { AppUser, AppTenant } from '../types';

interface LoginProps {
  onLogin: (data: { user: AppUser; tenant: AppTenant }) => void;
}

export const Login: React.FC<LoginProps> = ({ onLogin }) => {
  const [loading, setLoading] = useState(false);

  const handleEnter = async () => {
    setLoading(true);
    
    // Simula um pequeno delay para feedback visual
    await new Promise(resolve => setTimeout(resolve, 600));

    // Define um usuário padrão para a sessão
    onLogin({
      user: {
        id: 'admin',
        name: 'Administrador',
        email: 'admin@atendo.maps',
        tenantId: "1",
        profile: 'admin'
      },
      tenant: {
        id: "1",
        name: 'Atendo Maps'
      }
    });
    
    setLoading(false);
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

          <div className="space-y-6">
            <div className="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                <p className="text-[11px] text-slate-500 font-medium leading-relaxed">
                    Bem-vindo ao painel. Para configurar sua integração com CRM ou n8n, acesse o menu <span className="font-bold text-slate-700">Configurações</span> após entrar.
                </p>
            </div>

            <button
              onClick={handleEnter}
              disabled={loading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-3 text-sm tracking-wide uppercase"
            >
              {loading ? (
                <>
                    <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    Carregando...
                </>
              ) : (
                <>
                    Acessar Plataforma
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                </>
              )}
            </button>
          </div>
          
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
