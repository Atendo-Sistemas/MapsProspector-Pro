
import React, { useState, useEffect } from 'react';
import { CRMConfig, SearchHistoryItem, AppUser, AppTenant } from './types';
import { Prospecting } from './components/Prospecting';
import { StorageService } from './services/storage';

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

const App: React.FC = () => {
  // Estado de Usuário (Mockado para acesso direto)
  const [user, setUser] = useState<AppUser>({
    id: 'admin',
    name: 'Administrador',
    email: 'admin@atendo.maps',
    tenantId: "1",
    profile: 'admin'
  });

  const [activeTab, setActiveTab] = useState<'search' | 'history' | 'settings'>('search');
  const [history, setHistory] = useState<SearchHistoryItem[]>([]);
  
  // Atualizado para usar o tipo completo, incluindo leads
  const [selectedHistory, setSelectedHistory] = useState<SearchHistoryItem | undefined>();
  const [hasApiKey, setHasApiKey] = useState<boolean>(false);
  
  // GPS States
  const [userCoords, setUserCoords] = useState<{ latitude: number; longitude: number } | undefined>();
  const [userLocationName, setUserLocationName] = useState<string>(''); // Nome legível do local
  const [locStatus, setLocStatus] = useState<'idle' | 'loading' | 'success' | 'error' | 'https_error'>('idle');
  
  const [toastMsg, setToastMsg] = useState<string | null>(null);
  
  // Inicializa configurações direto do StorageService (Síncrono para evitar flash)
  const [config, setConfig] = useState<CRMConfig>(() => StorageService.getSettings());
  const [settingsForm, setSettingsForm] = useState<CRMConfig>(() => StorageService.getSettings());

  const refreshLocation = () => {
    // Verificação de segurança do navegador
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        setLocStatus('https_error');
        alert("⚠️ ERRO DE SEGURANÇA:\n\nOs navegadores bloqueiam GPS em sites sem HTTPS (cadeado).\n\nPara testar o GPS, acesse via localhost ou configure um domínio com SSL.");
        return;
    }

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
          console.warn("Geolocalização negada ou erro.", err);
          setLocStatus('error');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    } else {
      setLocStatus('error');
    }
  };

  useEffect(() => {
    refreshLocation();

    // Check for provided API Key
    const key = process.env.API_KEY;
    if (key && key.length > 5 && !key.includes("YOUR_API_KEY")) {
      setHasApiKey(true);
      console.log("System Status: API Key Loaded");
    } else {
      console.warn("System Status: API Key Missing or Invalid");
    }

    // Carrega histórico
    setHistory(StorageService.getHistory());
  }, []);

  const saveSettings = () => {
    StorageService.saveSettings(settingsForm);
    setConfig(settingsForm);
    setToastMsg('Configurações salvas no sistema!');
  };

  const clearHistory = () => {
    if (confirm('Deseja apagar o histórico de buscas?')) {
        StorageService.clearHistory();
        setHistory([]);
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

        <nav className="flex-grow p-6 space-y-2">
          <button 
            onClick={() => setActiveTab('search')} 
            className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'search' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            Prospecção
          </button>
          <button 
            onClick={() => { setHistory(StorageService.getHistory()); setActiveTab('history'); }} 
            className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'history' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Histórico
          </button>
          <button 
            onClick={() => setActiveTab('settings')} 
            className={`w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm ${activeTab === 'settings' ? 'bg-blue-600 text-white shadow-xl shadow-blue-900/30' : 'hover:bg-slate-800/50 text-slate-400'}`}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
            Configurações
          </button>
        </nav>
        
        <div className="p-6 border-t border-slate-800/50 space-y-4">
          <div className="bg-slate-800/40 p-5 rounded-[1.25rem] border border-slate-700/50">
            <p className="text-[9px] font-black text-slate-500 uppercase mb-2 tracking-widest">GPS Status</p>
            <p className="text-[10px] font-bold text-white truncate flex items-center gap-2 mb-2">
                <span className={`w-2 h-2 rounded-full ${
                    locStatus === 'success' ? 'bg-emerald-500 shadow-[0_0_8px_#10b981]' : 
                    locStatus === 'loading' ? 'bg-yellow-500 animate-pulse' : 
                    locStatus === 'https_error' ? 'bg-orange-500' : 'bg-red-500'
                }`}></span>
                {
                    locStatus === 'success' ? 'Localização Ativa' : 
                    locStatus === 'loading' ? 'Detectando...' : 
                    locStatus === 'https_error' ? 'Requer HTTPS' : 'Inativo / Bloqueado'
                }
            </p>
            {userLocationName && (
                <div className="pt-2 border-t border-slate-700/50">
                     <p className="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Detectado:</p>
                     <p className="text-[10px] text-emerald-300 font-bold leading-tight">{userLocationName}</p>
                </div>
            )}
            {locStatus === 'https_error' && (
                <div className="pt-2 border-t border-slate-700/50">
                     <p className="text-[9px] text-orange-400 font-bold leading-tight">Instale SSL/HTTPS para usar o GPS.</p>
                </div>
            )}
          </div>
        </div>
      </aside>

      {/* Área Principal */}
      <main className="flex-grow flex flex-col min-w-0">
        <header className="bg-white border-b border-slate-200 h-20 flex items-center px-10 justify-between sticky top-0 z-40 backdrop-blur-md bg-white/80">
          <div className="flex flex-col">
            <h2 className="text-slate-900 font-extrabold text-xl tracking-tight">
              {activeTab === 'search' ? 'Prospecção Inteligente' : activeTab === 'history' ? 'Arquivo de Buscas' : 'Integração CRM'}
            </h2>
            <p className="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Dashboard Atendo</p>
          </div>
          
          <div className="flex items-center gap-6">
             <button onClick={refreshLocation} className="flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all text-[10px] font-black uppercase text-slate-600">
                <svg className={`w-4 h-4 ${locStatus === 'loading' ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {locStatus === 'loading' ? 'Localizando...' : 'Recarregar GPS'}
             </button>
             <div className="flex flex-col items-end border-l border-slate-200 pl-6">
                <span className="text-xs font-bold text-slate-900">{user?.name}</span>
                <span className="text-[10px] font-bold text-slate-400 uppercase">Admin</span>
             </div>
          </div>
        </header>

        <div className="flex-grow overflow-y-auto p-10 bg-[#F8FAFC]">
          {activeTab === 'search' ? (
            <Prospecting 
                config={config} 
                initialHistoryItem={selectedHistory} // Passa o item completo
                userCoords={userCoords}
                userLocationName={userLocationName} 
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
          ) : (
            <div className="max-w-3xl mx-auto space-y-8 pb-20">
                <div className="bg-white p-10 rounded-[2.5rem] border border-slate-200 shadow-sm">
                  <h3 className="text-2xl font-black text-slate-900 mb-8 tracking-tight">Configurações de Conexão</h3>
                  
                  <div className="space-y-8">
                    {/* Google API Section */}
                    <div className="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                        <h4 className="font-black text-xl mb-4 flex items-center gap-2">Google Cloud API</h4>
                        {!hasApiKey ? (
                          <div className="p-5 bg-red-500/20 border border-red-500/50 rounded-2xl text-red-400 text-center font-bold">
                            ⚠️ API Key não detectada<br/>
                            <span className="text-[10px] font-normal opacity-80 mt-2 block">
                                Se você está rodando via Docker, certifique-se de ter rodado: <br/>
                                <code className="bg-black/30 px-2 py-1 rounded mt-1 inline-block">export API_KEY="sua_chave"</code> antes do build.
                            </span>
                          </div>
                        ) : (
                          <div className="p-5 bg-emerald-500/20 border border-emerald-500/50 rounded-2xl text-emerald-400 text-center font-bold">✓ Conectado ao Google Maps (Gemini)</div>
                        )}
                        <p className="text-[10px] text-slate-400 mt-4 text-center italic">API Key injetada via sistema.</p>
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
                        <label className="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Modelo de IA Inteligente</label>
                        <select className="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold appearance-none" value={settingsForm.selectedModel} onChange={(e) => setSettingsForm(prev => ({ ...prev, selectedModel: e.target.value as any }))}>
                            <option value="gemini-2.0-flash">Gemini 2.0 Flash (Estável - Padrão)</option>
                            <option value="gemini-2.5-flash">Gemini 2.5 Flash (Experimental Maps)</option>
                            <option value="gemini-2.0-flash-lite-preview-02-05">Gemini 2.0 Flash Lite (Rápido)</option>
                        </select>
                      </div>
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

                    <button onClick={saveSettings} className="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">Salvar Alterações</button>
                  </div>
                </div>
            </div>
          )}
        </div>
      </main>
    </div>
  );
};

export default App;
