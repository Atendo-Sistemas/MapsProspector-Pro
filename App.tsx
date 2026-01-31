
import React, { useState, useEffect, useCallback } from 'react';
import { CRMConfig, SearchHistoryItem, Lead, AppUser, AppTenant, TokenUsage } from './types';
import { Prospecting } from './components/Prospecting';
import { Companies } from './components/Companies';
import { Plans } from './components/Plans';
import { RequestCredits } from './components/RequestCredits';
import { CreditsAdmin } from './components/CreditsAdmin';
import { StorageService } from './services/storage';

const API_BASE = '';

interface AppProps {
  user: AppUser;
  tenant: AppTenant;
  tokenUsage?: TokenUsage;
  onLogout: () => void;
  /** Chamado quando a API de busca retorna tokenUsage atualizado (após cada busca) */
  onTokenUsageUpdate?: (tokenUsage: TokenUsage) => void;
}

/** Escapa um valor para célula CSV (vírgula, aspas, quebra de linha). */
function escapeCsv(val: string): string {
  if (val == null) return '';
  const s = String(val).trim();
  if (s.includes(',') || s.includes('"') || s.includes('\n') || s.includes('\r')) {
    return '"' + s.replace(/"/g, '""') + '"';
  }
  return s;
}

/** Exporta todas as pesquisas do histórico para um arquivo CSV (abre no Excel). Apenas leads desbloqueados. */
function exportAllToExcel(history: SearchHistoryItem[], onNoUnlocked?: () => void): void {
  const rows: string[][] = [];
  const headers = ['Pesquisa', 'Local', 'Tag', 'Data da Pesquisa', 'Nome', 'Telefone', 'Email', 'Endereço', 'CNPJ', 'Sócios', 'Site', 'Maps'];
  rows.push(headers);

  for (const item of history) {
    const leads = (item.leads ?? []).filter((l) => l.locked === false);
    const dateStr = item.timestamp ? new Date(item.timestamp).toLocaleString('pt-BR') : '';
    for (const lead of leads) {
      rows.push([
      escapeCsv(item.query),
      escapeCsv(item.location),
      escapeCsv(item.tag),
      escapeCsv(dateStr),
      escapeCsv(lead.name),
      escapeCsv(lead.phone ?? ''),
      escapeCsv(lead.email ?? ''),
      escapeCsv(lead.address),
      escapeCsv(lead.cnpj ?? ''),
      escapeCsv(lead.partners ?? ''),
      escapeCsv(lead.website ?? ''),
      escapeCsv(lead.mapsUri ?? '')
      ]);
    }
  }

  if (rows.length <= 1) {
    onNoUnlocked?.();
    return;
  }

  const csvContent = rows.map(row => row.join(';')).join('\r\n');
  const BOM = '\uFEFF';
  const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `pesquisas_export_${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// Componente Toast Interno
const Toast = ({ message, onClose }: { message: string; onClose: () => void }) => {
  useEffect(() => {
    const timer = setTimeout(onClose, 3000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <div className="fixed bottom-10 right-10 bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4 z-[9999] animate-[slideIn_0.3s_ease-out]">
      <div className="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white">
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" /></svg>
      </div>
      <div>
        <h4 className="font-bold text-sm">Sucesso</h4>
        <p className="text-xs text-slate-300">{message}</p>
      </div>
    </div>
  );
};

const App: React.FC<AppProps> = ({ user, tenant, tokenUsage, onLogout, onTokenUsageUpdate }) => {
  const [activeTab, setActiveTab] = useState<'dashboard' | 'search' | 'history' | 'request-credits' | 'settings' | 'companies' | 'plans' | 'credits'>('dashboard');
  const [history, setHistory] = useState<SearchHistoryItem[]>([]);
  
  // Atualizado para usar o tipo completo, incluindo leads
  const [selectedHistory, setSelectedHistory] = useState<SearchHistoryItem | undefined>();
  
  // GPS States
  const [userCoords, setUserCoords] = useState<{ latitude: number; longitude: number } | undefined>();
  const [userLocationName, setUserLocationName] = useState<string>(''); // Nome legível do local
  const [locStatus, setLocStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
  
  const [toastMsg, setToastMsg] = useState<string | null>(null);
  const [companiesRefreshKey, setCompaniesRefreshKey] = useState(0);
  const [plansRefreshKey, setPlansRefreshKey] = useState(0);
  const [creditsRefreshKey, setCreditsRefreshKey] = useState(0);
  
  // Inicializa configurações direto do StorageService (Síncrono para evitar flash)
  const [config, setConfig] = useState<CRMConfig>(() => StorageService.getSettings());
  const [settingsForm, setSettingsForm] = useState<CRMConfig>(() => StorageService.getSettings());

  const refreshLocation = () => {
    setLocStatus('loading');
    setUserLocationName('');
    
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        async (pos) => {
          const { latitude, longitude } = pos.coords;
          setUserCoords({ latitude, longitude });
          setLocStatus('success');

          // Reverse Geocoding (Gratuito via OSM Nominatim para UX)
          try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=10`); // Zoom reduzido para pegar contexto macro
            if (res.ok) {
                const data = await res.json();
                const addr = data.address;
                
                // MUDANÇA: Ignora bairro (suburb/neighbourhood) para focar na CIDADE inteira
                const city = addr.city || addr.town || addr.municipality || addr.village || '';
                const state = addr.state_district || addr.state || '';
                
                let readable = '';
                if (city) readable = city;
                if (readable && state) readable += ` - ${state}`;
                
                if (readable) setUserLocationName(readable);
            }
          } catch (e) {
            console.warn("Não foi possível obter o nome do endereço via GPS", e);
          }
        },
        (err) => {
          console.warn("Geolocalização negada.", err);
          setLocStatus('error');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    } else {
      setLocStatus('error');
    }
  };

  const loadHistory = useCallback(async () => {
    try {
      const res = await fetch(`${API_BASE}/api/history.php`, { credentials: 'include' });
      const data = await res.json();
      if (data.success && Array.isArray(data.data)) {
        const normalized = data.data.map((item: Record<string, unknown>) => {
          const apiItem = {
            ...item,
            id: String(item.id),
            resultsCount: (item.results_count ?? item.resultsCount) as number,
          } as SearchHistoryItem;
          if (apiItem.leads && Array.isArray(apiItem.leads)) {
            apiItem.leads = apiItem.leads.map((l: Lead) => ({
              id: l.id,
              name: l.name ?? '',
              locked: l.locked === false ? false : true,
              dbId: l.dbId,
              ...(l.locked === false ? { phone: l.phone, email: l.email, address: l.address, website: l.website, mapsUri: l.mapsUri, cnpj: l.cnpj, partners: l.partners } : {}),
            }));
          }
          return apiItem;
        }) as SearchHistoryItem[];
        setHistory(normalized);
        StorageService.saveHistory(normalized);
      }
    } catch {
      setHistory(StorageService.getHistory());
    }
  }, []);

  useEffect(() => {
    refreshLocation();
    loadHistory();

    const loadServerSettings = async () => {
      try {
        const response = await fetch('/api/settings.php');
        const data = await response.json();
        if (data.success && data.data) {
          const serverConfig = {
            ...settingsForm,
            ...data.data
          };
          setSettingsForm(serverConfig);
          setConfig(serverConfig);
          StorageService.saveSettings(serverConfig);
        }
      } catch (error) {
        console.error('Erro ao carregar configurações do servidor:', error);
      }
    };
    
    loadServerSettings();
  }, [loadHistory]);

  const saveSettings = async () => {
    try {
      // Salva no backend
      const response = await fetch('/api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settingsForm)
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Salva também no localStorage
        StorageService.saveSettings(settingsForm);
        setConfig(settingsForm);
        setToastMsg('Configurações salvas com sucesso!');
      } else {
        setToastMsg('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
      }
    } catch (error: any) {
      console.error('Erro ao salvar configurações:', error);
      setToastMsg('Erro de conexão ao salvar configurações');
    }
  };

  const clearHistory = async () => {
    if (!confirm('Deseja apagar o histórico de buscas?')) return;
    try {
      const res = await fetch(`${API_BASE}/api/history.php`, { method: 'DELETE', credentials: 'include' });
      const data = await res.json();
      if (data.success) {
        StorageService.clearHistory();
        setHistory([]);
        setToastMsg('Histórico limpo com sucesso.');
      } else {
        setToastMsg(data.error || 'Erro ao limpar histórico.');
      }
    } catch {
      setToastMsg('Erro de conexão ao limpar histórico.');
    }
  };

  const useHistoryItem = (item: SearchHistoryItem) => {
    // Passa o objeto completo, agora contendo os leads
    setSelectedHistory(item);
    setActiveTab('search');
  };

  return (
    <div className="flex h-screen bg-slate-50 font-sans antialiased text-slate-900 overflow-hidden">
      {toastMsg && <Toast message={toastMsg} onClose={() => setToastMsg(null)} />}
      
      {/* Sidebar Lateral */}
      <aside className="w-72 bg-[#0F172A] text-white flex flex-col shrink-0 shadow-2xl z-50">
        <div className="p-8 border-b border-slate-800/50">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-900/40 border border-blue-400/20">
              <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <div>
              <span className="font-black text-lg block leading-none tracking-tight italic">ATENDO</span>
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Maps Prospector</span>
            </div>
          </div>
        </div>

        <nav className="flex-grow p-6 flex flex-col gap-0">
          {/* Normal: para todos */}
          <div className="space-y-2 pb-4">
            <p className="text-[11px] font-black text-slate-400 uppercase tracking-widest px-5 mb-3">Normal</p>
            <button 
              onClick={() => setActiveTab('dashboard')} 
              className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'dashboard' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
              Dashboard
            </button>
            <button 
              onClick={() => setActiveTab('search')} 
              className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'search' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
              Prospecção
            </button>
            <button 
              onClick={() => { loadHistory(); setActiveTab('history'); }} 
              className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'history' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              Histórico
            </button>
            <button 
              onClick={() => setActiveTab('request-credits')} 
              className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'request-credits' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              Solicitar Créditos
            </button>
          </div>

          {/* Linha divisória visível */}
          <div className="h-px w-full bg-slate-600/80 my-2" aria-hidden="true" />

          {/* Administração: apenas super_admin */}
          {String(user.profile).toLowerCase() === 'super_admin' && (
            <div className="space-y-2 pt-2 pb-4">
              <p className="text-[11px] font-black text-slate-400 uppercase tracking-widest px-5 mb-3">Administração</p>
              <button 
                onClick={() => { setActiveTab('plans'); setPlansRefreshKey((k) => k + 1); }} 
                className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'plans' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Planos
              </button>
              <button 
                onClick={() => { setActiveTab('companies'); setCompaniesRefreshKey((k) => k + 1); }} 
                className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'companies' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                Empresas
              </button>
              <button 
                onClick={() => { setActiveTab('credits'); setCreditsRefreshKey((k) => k + 1); }} 
                className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'credits' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Créditos
              </button>
            </div>
          )}

          {/* Linha divisória antes de Configurações */}
          <div className="h-px w-full bg-slate-600/80 my-2" aria-hidden="true" />

          <div className="space-y-2 pt-2">
            <button 
              onClick={() => setActiveTab('settings')} 
              className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'settings' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
              Configurações
            </button>
          </div>
        </nav>
        
        <div className="p-6 border-t border-slate-800/50 space-y-4">
          <div className="bg-slate-800/40 p-5 rounded-[1.25rem] border border-slate-700/50">
            <p className="text-[9px] font-black text-slate-500 uppercase mb-2 tracking-widest">GPS Status</p>
            <p className="text-[10px] font-bold text-white truncate flex items-center gap-2 mb-2">
                <span className={`w-2 h-2 rounded-full ${locStatus === 'success' ? 'bg-emerald-500 shadow-[0_0_8px_#10b981]' : locStatus === 'loading' ? 'bg-yellow-500 animate-pulse' : 'bg-red-500'}`}></span>
                {locStatus === 'success' ? 'Localização Ativa' : locStatus === 'loading' ? 'Detectando...' : 'GPS Inativo'}
            </p>
            {userLocationName && (
                <div className="pt-2 border-t border-slate-700/50">
                     <p className="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Detectado:</p>
                     <p className="text-[10px] text-emerald-300 font-bold leading-tight">{userLocationName}</p>
                </div>
            )}
          </div>
        </div>
      </aside>

      {/* Área Principal */}
      <main className="flex-grow flex flex-col min-w-0">
        <div className="sticky top-0 z-50 bg-white shadow-sm">
          {(tokenUsage?.limitReached || tenant?.status === 'suspended') && (
            <div className="bg-amber-500 text-amber-950 px-10 py-3 flex items-center justify-center gap-2 text-sm font-bold">
              <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              <span>Sua empresa atingiu o limite de tokens do plano. Entre em contato para aquisição de mais tokens.</span>
            </div>
          )}
          <header className="bg-white border-b border-slate-200 h-20 flex items-center px-10 justify-between backdrop-blur-md bg-white/80">
          <div className="flex flex-col">
            <h2 className="text-slate-900 font-extrabold text-xl tracking-tight">
              {activeTab === 'dashboard' ? 'Dashboard' : activeTab === 'search' ? 'Prospecção Inteligente' : activeTab === 'history' ? 'Arquivo de Buscas' : activeTab === 'request-credits' ? 'Solicitar Créditos' : activeTab === 'companies' ? 'Empresas' : activeTab === 'plans' ? 'Planos' : activeTab === 'credits' ? 'Créditos' : 'Integração CRM'}
            </h2>
            <p className="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Dashboard Atendo</p>
          </div>
          
          <div className="flex items-center gap-6">
             <button onClick={refreshLocation} className="flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all text-[10px] font-black uppercase text-slate-600">
                <svg className={`w-4 h-4 ${locStatus === 'loading' ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {locStatus === 'loading' ? 'Localizando...' : 'Recarregar GPS'}
             </button>
             <div className="flex items-center gap-4 border-l border-slate-200 pl-6">
                <div className="flex flex-col items-end">
                  <span className="text-xs font-bold text-slate-900">{user.name}</span>
                  <span className="text-[10px] font-bold text-slate-400 uppercase">{tenant.name}</span>
                </div>
                <button onClick={onLogout} className="text-[10px] font-bold text-slate-500 hover:text-red-600 uppercase" title="Sair">Sair</button>
             </div>
          </div>
        </header>
        </div>

        <div className="flex-grow overflow-y-auto p-10 bg-[#F8FAFC]">
          {activeTab === 'dashboard' ? (
            <div className="max-w-4xl mx-auto">
              <h3 className="text-2xl font-black text-slate-900 mb-8">Estatísticas da conta</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div className="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                  <div className="flex items-center gap-4 mb-4">
                    <div className="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                      <svg className="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    <div>
                      <p className="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens utilizados</p>
                      <p className="text-3xl font-black text-slate-900">{tokenUsage?.used ?? 0}</p>
                    </div>
                  </div>
                  <p className="text-xs text-slate-500">Tokens usados no período (cada página de 20 resultados = 1 token)</p>
                </div>
                <div className="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                  <div className="flex items-center gap-4 mb-4">
                    <div className="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center">
                      <svg className="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                    <div>
                      <p className="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens permitidos (plano)</p>
                      <p className="text-3xl font-black text-slate-900">
                        {tokenUsage != null && tokenUsage.limit === 0 ? 'Ilimitado' : (tokenUsage?.limit ?? '—')}
                      </p>
                    </div>
                  </div>
                  <p className="text-xs text-slate-500">Limite do plano vinculado à sua empresa neste período</p>
                </div>
                <div className="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm sm:col-span-2 lg:col-span-1">
                  <div className="flex items-center gap-4 mb-4">
                    <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${(tokenUsage?.limitReached || tenant?.status === 'suspended') ? 'bg-amber-100' : 'bg-slate-100'}`}>
                      <svg className={`w-7 h-7 ${(tokenUsage?.limitReached || tenant?.status === 'suspended') ? 'text-amber-600' : 'text-slate-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    </div>
                    <div>
                      <p className="text-[10px] font-black text-slate-400 uppercase tracking-wider">Disponível</p>
                      <p className="text-3xl font-black text-slate-900">
                        {tokenUsage != null && tokenUsage.limit === 0
                          ? 'Ilimitado'
                          : tokenUsage != null
                            ? Math.max(0, tokenUsage.limit - tokenUsage.used)
                            : '—'}
                      </p>
                    </div>
                  </div>
                  <p className="text-xs text-slate-500">Tokens restantes para novas buscas neste período</p>
                </div>
              </div>
              <div className="mt-8 p-6 bg-slate-50 rounded-2xl border border-slate-200">
                <p className="text-sm font-bold text-slate-700">
                  <span className="text-slate-500">Empresa:</span> {tenant?.name ?? '—'}
                </p>
                {!tenant?.id && (
                  <p className="text-xs text-slate-500 mt-2">Conta plataforma (Super Admin) — não há limite de tokens por empresa.</p>
                )}
              </div>
            </div>
          ) : activeTab === 'request-credits' ? (
            <RequestCredits />
          ) : activeTab === 'credits' ? (
            String(user.profile).toLowerCase() === 'super_admin' ? (
              <CreditsAdmin refreshKey={creditsRefreshKey} />
            ) : (
              <div className="max-w-4xl mx-auto py-24 text-center text-slate-500 font-bold">Acesso restrito ao administrador da plataforma.</div>
            )
          ) : activeTab === 'companies' ? (
            String(user.profile).toLowerCase() === 'super_admin' ? (
              <Companies refreshKey={companiesRefreshKey} />
            ) : (
              <div className="max-w-4xl mx-auto py-24 text-center text-slate-500 font-bold">Acesso restrito ao administrador da plataforma.</div>
            )
          ) : activeTab === 'plans' ? (
            String(user.profile).toLowerCase() === 'super_admin' ? (
              <Plans refreshKey={plansRefreshKey} />
            ) : (
              <div className="max-w-4xl mx-auto py-24 text-center text-slate-500 font-bold">Acesso restrito ao administrador da plataforma.</div>
            )
          ) : activeTab === 'search' ? (
            <Prospecting 
                config={config} 
                initialHistoryItem={selectedHistory}
                userCoords={userCoords}
                userLocationName={userLocationName}
                onExportToExcel={() => { loadHistory(); exportAllToExcel(history, () => setToastMsg('Nenhum lead desbloqueado para exportar. Desbloqueie leads nas pesquisas para incluí-los no Excel.')); }}
                tokenUsage={tokenUsage}
                tenantStatus={tenant?.status}
                onTokenUsageUpdate={onTokenUsageUpdate}
            />
          ) : activeTab === 'history' ? (
            <div className="max-w-5xl mx-auto">
                <div className="flex justify-between items-center mb-10">
                    <div><h3 className="text-2xl font-black text-slate-900">Histórico Recente</h3></div>
                    <button onClick={clearHistory} className="text-xs font-extrabold text-red-500 hover:bg-red-50 px-5 py-2 rounded-xl">Limpar Tudo</button>
                </div>
                <div className="space-y-4">
                    {history.length === 0 ? (
                        <div className="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
                            <p className="text-slate-400 font-bold uppercase text-[10px]">Nenhum registro encontrado</p>
                        </div>
                    ) : (
                        history.map(item => (
                            <div key={item.id} className="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between group hover:border-blue-300 transition-all hover:shadow-md">
                                <div className="flex items-center gap-6">
                                    <div className="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-xl">🔎</div>
                                    <div>
                                        <h4 className="font-extrabold text-slate-900 text-lg capitalize">{item.query}</h4>
                                        <p className="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">📍 {item.location || 'Local Automático'} <span className="ml-2 text-blue-500">({item.resultsCount} leads)</span></p>
                                    </div>
                                </div>
                                <button onClick={() => useHistoryItem(item)} className="bg-slate-900 text-white font-bold px-6 py-3 rounded-2xl text-xs hover:bg-blue-600 transition-colors">Ver Novamente</button>
                            </div>
                        ))
                    )}
                </div>
            </div>
          ) : activeTab === 'settings' ? (
            <div className="max-w-3xl mx-auto space-y-8 pb-20">
                <div className="bg-white p-10 rounded-[2.5rem] border border-slate-200 shadow-sm">
                  <h3 className="text-2xl font-black text-slate-900 mb-8 tracking-tight">Configurações de Conexão</h3>
                  
                  <div className="space-y-8">
                    {/* API de Busca: status para todos; chave apenas super_admin. Nome Thordata só para super_admin. */}
                    <div className="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                        <h4 className="font-black text-xl mb-4 flex items-center gap-2">{String(user.profile).toLowerCase() === 'super_admin' ? 'API Thordata (ScraperAPI)' : 'API de Busca (Google Maps)'}</h4>
                        {(String(user.profile).toLowerCase() === 'super_admin' ? settingsForm.scraperApiKey?.trim() : settingsForm.scraperApiKeyConfigured) ? (
                          <>
                            <div className="p-5 bg-emerald-500/20 border border-emerald-500/50 rounded-2xl text-emerald-400 text-center font-bold">{String(user.profile).toLowerCase() === 'super_admin' ? '✓ Conectado ao Google Maps via Thordata' : '✓ Conectado ao Google Maps'}</div>
                            <p className="text-[10px] text-slate-400 mt-4 text-center italic">{String(user.profile).toLowerCase() === 'super_admin' ? 'API configurada no servidor. Todas as empresas utilizam esta chave.' : 'API configurada no servidor pelo administrador da plataforma.'}</p>
                          </>
                        ) : (
                          <>
                            <div className="p-5 bg-amber-500/20 border border-amber-500/50 rounded-2xl text-amber-400 text-center font-bold">{String(user.profile).toLowerCase() === 'super_admin' ? 'Nenhuma API Thordata configurada' : 'Nenhuma API de busca configurada'}</div>
                            <p className="text-[10px] text-slate-400 mt-4 text-center italic">{String(user.profile).toLowerCase() === 'super_admin' ? 'Configure a chave abaixo para que todas as empresas possam buscar leads no Google Maps.' : 'O administrador da plataforma deve configurar a chave nas Configurações.'}</p>
                          </>
                        )}
                    </div>

                    {/* Botões de Opção Rápida */}
                    <div className="grid grid-cols-1 gap-4">
                        <div className="bg-emerald-50 p-6 rounded-2xl border border-emerald-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h5 className="font-black text-slate-900 text-sm">Modo Estrito (Fix 400: number/ticketId)</h5>
                                    <p className="text-[10px] text-slate-500 font-medium">Obrigatório para Atendo/Evolution API. Envia apenas o essencial.</p>
                                </div>
                                <button 
                                    onClick={() => setSettingsForm(prev => ({ ...prev, simplifiedPayload: !prev.simplifiedPayload }))}
                                    className={`w-12 h-6 rounded-full transition-all relative ${settingsForm.simplifiedPayload ? 'bg-emerald-600' : 'bg-slate-300'}`}
                                >
                                    <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${settingsForm.simplifiedPayload ? 'left-7' : 'left-1'}`}></div>
                                </button>
                            </div>
                        </div>

                        <div className="bg-amber-50 p-6 rounded-2xl border border-amber-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h5 className="font-black text-slate-900 text-sm">Contornar CORS (Modo Proxy)</h5>
                                    <p className="text-[10px] text-slate-500 font-medium">Ative se houver erro ao conectar com seu n8n/webhook.</p>
                                </div>
                                <button 
                                    onClick={() => setSettingsForm(prev => ({ ...prev, useProxy: !prev.useProxy }))}
                                    className={`w-12 h-6 rounded-full transition-all relative ${settingsForm.useProxy ? 'bg-amber-600' : 'bg-slate-300'}`}
                                >
                                    <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${settingsForm.useProxy ? 'left-7' : 'left-1'}`}></div>
                                </button>
                            </div>
                        </div>

                        <div className="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h5 className="font-black text-slate-900 text-sm">Encapsular Dados (Wrap em 'body')</h5>
                                    <p className="text-[10px] text-slate-500 font-medium">Necessário para alguns Webhooks do n8n.</p>
                                </div>
                                <button 
                                    onClick={() => setSettingsForm(prev => ({ ...prev, wrapInBody: !prev.wrapInBody }))}
                                    className={`w-12 h-6 rounded-full transition-all relative ${settingsForm.wrapInBody ? 'bg-blue-600' : 'bg-slate-300'}`}
                                >
                                    <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${settingsForm.wrapInBody ? 'left-7' : 'left-1'}`}></div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Nome da Instância</label>
                        <input type="text" className="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" value={settingsForm.tenantName || ''} onChange={(e) => setSettingsForm(prev => ({ ...prev, tenantName: e.target.value }))} />
                      </div>
                    </div>

                    <div className="pt-4">
                      <label className="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">URL do Webhook n8n ou CRM Atendo</label>
                      <input type="url" className="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" placeholder="https://seu-n8n.com/webhook/..." value={settingsForm.baseUrl} onChange={(e) => setSettingsForm(prev => ({ ...prev, baseUrl: e.target.value }))} />
                    </div>

                    <div>
                      <label className="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Token de Acesso / API Key CRM</label>
                      <input type="password" className="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" placeholder="Insira o Token do CRM" value={settingsForm.token} onChange={(e) => setSettingsForm(prev => ({ ...prev, token: e.target.value }))} />
                    </div>

                    {String(user.profile).toLowerCase() === 'super_admin' && (
                    <div className="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                      <div className="absolute top-0 right-0 w-32 h-32 bg-purple-600/10 rounded-full blur-3xl"></div>
                      <h4 className="font-black text-lg mb-3 flex items-center gap-2">ScraperAPI Thordata</h4>
                      <p className="text-xs text-slate-400 mb-4">Chave de API para busca direta no Google Maps. Apenas o Super Admin pode alterar; todas as empresas utilizam esta chave.</p>
                      <div>
                        <label className="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Chave da API Thordata</label>
                        <input type="password" className="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-purple-500 font-bold text-white placeholder:text-slate-500" placeholder="Insira a chave da API Thordata" value={settingsForm.scraperApiKey || ''} onChange={(e) => setSettingsForm(prev => ({ ...prev, scraperApiKey: e.target.value }))} />
                      </div>
                    </div>
                    )}

                    <button onClick={saveSettings} className="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">Salvar Alterações</button>
                  </div>
                </div>
            </div>
          ) : null}
        </div>
      </main>
    </div>
  );
};

export default App;
