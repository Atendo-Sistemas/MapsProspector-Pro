
import React, { useState, useEffect } from 'react';
import { Lead, CRMConfig, CRMContact, SearchHistoryItem } from '../types';
import { searchLeadsOnMaps } from '../services/gemini';
import { sendSingleToCRM } from '../services/api';
import { StorageService } from '../services/storage';

interface ProspectingProps {
  config: CRMConfig;
  initialHistoryItem?: SearchHistoryItem; // Mudança: Recebe o item completo do histórico
  userCoords?: { latitude: number; longitude: number };
  userLocationName?: string;
}

export const Prospecting: React.FC<ProspectingProps> = ({ config, initialHistoryItem, userCoords, userLocationName }) => {
  const [query, setQuery] = useState('');
  const [location, setLocation] = useState('');
  const [tag, setTag] = useState('');
  const [useGPS, setUseGPS] = useState(false);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(false);
  const [sendingIndividual, setSendingIndividual] = useState<string | null>(null);
  const [errorInfo, setErrorInfo] = useState<string | null>(null);
  
  // Controle de Paginação Local
  const [visibleCount, setVisibleCount] = useState(12);

  // Efeito para carregar dados iniciais (Props ou Histórico)
  useEffect(() => {
    if (initialHistoryItem) {
      // Prioridade 1: Veio do clique no "Ver Novamente" do histórico
      loadFromHistoryItem(initialHistoryItem);
    } else {
      // Prioridade 2: Carregar a última busca salva no navegador
      const lastSearch = StorageService.getLastSearch();
      if (lastSearch) {
        loadFromHistoryItem(lastSearch);
      }
    }
  }, [initialHistoryItem]);

  const loadFromHistoryItem = (item: SearchHistoryItem) => {
      setQuery(item.query);
      setLocation(item.location);
      setTag(item.tag);
      if (item.leads && item.leads.length > 0) {
          setLeads(item.leads);
      }
  };

  const performSearch = async () => {
    const cleanQuery = query.trim();
    const cleanLocation = location.trim();

    if (!cleanQuery) {
      setErrorInfo('Digite o ramo de atividade (Ex: Petshop).');
      return;
    }
    if (!useGPS && !cleanLocation) {
      setErrorInfo('Digite a cidade ou ative o GPS.');
      return;
    }

    setLoading(true);
    setErrorInfo(null);
    setLeads([]);
    setVisibleCount(12); // Reseta paginação

    try {
      // O serviço agora tenta buscar 50-100+ leads
      const results = await searchLeadsOnMaps(
        cleanQuery, 
        useGPS ? undefined : cleanLocation, 
        [], 
        config.selectedModel || 'gemini-2.5-flash',
        useGPS ? userCoords : undefined,
        useGPS ? userLocationName : undefined
      );
      
      if (!results || results.length === 0) {
        setErrorInfo(`Nenhum resultado encontrado para "${cleanQuery}" ${useGPS ? (userLocationName ? `em ${userLocationName}` : 'ao seu redor') : `em ${cleanLocation}`}. Tente um termo mais amplo.`);
      } else {
        setLeads(results);

        // SALVAR NO HISTÓRICO APÓS SUCESSO (INCLUINDO OS LEADS)
        const historyItem: SearchHistoryItem = {
            id: Date.now().toString(),
            query: cleanQuery,
            location: useGPS ? (userLocationName || 'Localização GPS') : cleanLocation,
            tag: tag,
            timestamp: new Date().toISOString(),
            resultsCount: results.length,
            leads: results // Agora salvamos os dados completos
        };
        StorageService.addToHistory(historyItem);
      }
    } catch (err: any) {
      let cleanMsg = err.message;
      try {
         const parsed = JSON.parse(cleanMsg);
         cleanMsg = parsed.message || cleanMsg;
      } catch(e) {}
      setErrorInfo(cleanMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleSendSingle = async (lead: Lead) => {
    // VALIDAÇÃO PRÉVIA DE CONFIGURAÇÃO
    if (!config.baseUrl || config.baseUrl.trim() === '') {
        alert("⚠️ CONFIGURAÇÃO NECESSÁRIA\n\nPara exportar este lead, você precisa configurar a URL do seu CRM ou Webhook (n8n).\n\n1. Acesse o menu 'Configurações' na barra lateral.\n2. Preencha a URL do Webhook.\n3. Salve e tente novamente.");
        return;
    }

    setSendingIndividual(lead.id);
    try {
      const contact: CRMContact = {
        name: lead.name,
        number: lead.phone || "",
        email: lead.email || "",
        cnpj: lead.cnpj || "",
        tag: tag || "prospect_maps",
        commentary: `Lead capturado via Maps. Endereço: ${lead.address}`,
        extraInfo: [
            { name: 'Endereço', value: lead.address },
            { name: 'CNPJ', value: lead.cnpj || 'Não identificado' },
            { name: 'Sócios', value: lead.partners || 'Não identificado' },
            { name: 'Maps', value: lead.mapsUri || '' },
            { name: 'Site', value: lead.website || '' }
        ]
      };
      await sendSingleToCRM(config, contact);
      alert('✅ Enviado com sucesso para o CRM!');
    } catch (err: any) {
      alert(`❌ Erro no Envio: ${err.message}`);
    } finally {
      setSendingIndividual(null);
    }
  };

  const handleLoadMore = () => {
    setVisibleCount(prev => prev + 12);
  };

  // Fatia o array total para mostrar apenas o count atual
  const visibleLeads = leads.slice(0, visibleCount);

  return (
    <div className="max-w-[1400px] mx-auto">
      {/* Barra de Busca */}
      <div className="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-10">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div className="md:col-span-4">
            <label className="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca?</label>
            <input
              className="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all"
              placeholder="Ex: Petshop, Clínica, Padaria..."
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && performSearch()}
            />
          </div>
          <div className="md:col-span-4">
            <div className="flex justify-between items-center mb-1">
              <label className="block text-[10px] font-black text-slate-400 uppercase ml-1 tracking-widest">Onde?</label>
              <button onClick={() => setUseGPS(!useGPS)} className={`text-[9px] font-black px-2 py-0.5 rounded-full transition-all ${useGPS ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'}`}>
                {useGPS ? 'GPS ATIVO' : 'USAR MEU GPS'}
              </button>
            </div>
            {!useGPS ? (
              <input
                className="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all"
                placeholder="Cidade ou Região"
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                onKeyPress={(e) => e.key === 'Enter' && performSearch()}
              />
            ) : (
              <div className="w-full px-5 py-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-bold flex items-center gap-2 overflow-hidden">
                <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse flex-shrink-0"></span>
                <span className="truncate">
                    {userLocationName ? userLocationName : "Detectando sua localização..."}
                </span>
              </div>
            )}
          </div>
          <div className="md:col-span-2">
            <label className="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Tag CRM</label>
            <input
              className="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm"
              placeholder="Ex: leads_novos"
              value={tag}
              onChange={(e) => setTag(e.target.value)}
            />
          </div>
          <div className="md:col-span-2 flex items-end">
            <button
              onClick={performSearch}
              disabled={loading}
              className="w-full py-3 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-xl shadow-slate-100 disabled:opacity-50 flex items-center justify-center uppercase tracking-wider text-xs"
            >
              {loading ? (
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              ) : 'Buscar Tudo'}
            </button>
          </div>
        </div>
      </div>

      {errorInfo && (
        <div className="mb-8 p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm animate-fade-in">
          <div className="flex items-center gap-3">
            <span className="text-xl">⚠️</span>
            <p className="text-xs font-bold leading-relaxed">{errorInfo}</p>
          </div>
        </div>
      )}

      {/* Contador de Resultados */}
      {leads.length > 0 && (
         <div className="mb-6 flex justify-between items-end px-2">
            <div>
                <h3 className="text-xl font-black text-slate-900 tracking-tight">Resultados da Busca</h3>
                <p className="text-xs text-slate-500 font-medium">
                   Exibindo <span className="font-bold text-slate-900">{Math.min(visibleCount, leads.length)}</span> de <span className="font-bold text-slate-900">{leads.length}</span> empresas encontradas
                </p>
            </div>
         </div>
      )}

      {/* Grid de Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
        {visibleLeads.map((lead) => (
          <div 
            key={lead.id} 
            className="relative mt-4 pt-10 pb-6 px-6 bg-white border border-slate-200 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group"
          >
            {/* Badge Flutuante */}
            <div className="absolute -top-3 left-6 bg-blue-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-blue-200 z-10">
              {lead.partners ? 'Dados Ricos' : 'Lead'}
            </div>

            {/* Conteúdo */}
            <div>
                {/* Nome / Razão Social */}
                <h3 className="font-extrabold text-slate-900 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">
                    {lead.name}
                </h3>

                {/* Dados de Contato */}
                <div className="space-y-3 mb-6">
                    {/* Telefone (Destaque Verde) */}
                    <div className="flex items-center gap-3 bg-emerald-50/50 p-2 rounded-lg -mx-2">
                        <span className="text-emerald-500 text-xs">📞</span>
                        <span className="text-emerald-700 font-bold text-xs">
                            {lead.phone || 'Sem telefone'}
                        </span>
                    </div>

                    {/* Sócios (Novo) */}
                    {lead.partners && (
                        <div className="flex items-start gap-3">
                            <span className="text-purple-400 text-xs mt-0.5">👥</span>
                            <div className="flex flex-col">
                                <span className="text-[9px] font-black text-slate-400 uppercase">Sócios/Resp.</span>
                                <p className="text-slate-700 font-bold text-[10px] leading-tight">
                                    {lead.partners}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* CNPJ (Novo Layout) */}
                    {lead.cnpj && (
                        <div className="flex items-center gap-3">
                            <span className="text-amber-400 text-xs">🏢</span>
                            <div className="flex flex-col">
                                <span className="text-[9px] font-black text-slate-400 uppercase">CNPJ</span>
                                <span className="text-slate-700 font-mono font-bold text-[10px]">
                                    {lead.cnpj}
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Email */}
                    {lead.email && (
                        <div className="flex items-center gap-3">
                            <span className="text-blue-400 text-xs">✉️</span>
                             <div className="flex flex-col overflow-hidden">
                                <span className="text-[9px] font-black text-slate-400 uppercase">Email</span>
                                <a href={`mailto:${lead.email}`} className="text-blue-600 font-bold text-[10px] hover:underline truncate w-full block">
                                    {lead.email}
                                </a>
                            </div>
                        </div>
                    )}

                    {/* Endereço */}
                    <div className="flex items-start gap-3 pt-2 border-t border-slate-100">
                        <span className="text-rose-400 text-xs mt-0.5">📍</span>
                        <p className="text-slate-500 font-semibold text-[10px] leading-relaxed line-clamp-2">
                            {lead.address}
                        </p>
                    </div>
                </div>

                {/* Fontes (Opcional visual) */}
                <div className="flex gap-2 mb-6 opacity-60">
                     <span className="bg-slate-100 text-slate-400 text-[8px] font-bold px-2 py-1 rounded uppercase">Fonte 1</span>
                     {lead.sources && lead.sources.length > 1 && (
                        <span className="bg-slate-100 text-slate-400 text-[8px] font-bold px-2 py-1 rounded uppercase">Fonte 2</span>
                     )}
                </div>
            </div>

            {/* Footer com Botões */}
            <div className="flex items-center gap-3 pt-4 border-t border-slate-100 mt-auto">
                <button 
                    onClick={() => handleSendSingle(lead)}
                    disabled={sendingIndividual === lead.id}
                    className="flex-grow bg-white border border-slate-200 text-slate-900 hover:bg-slate-50 hover:border-slate-300 font-black text-[10px] py-3 rounded-xl uppercase tracking-wide transition-all shadow-sm active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2"
                >
                    {sendingIndividual === lead.id ? 'Enviando...' : 'Exportar para CRM'}
                </button>
                
                {lead.mapsUri && (
                    <a 
                        href={lead.mapsUri}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-12 h-[38px] flex-shrink-0 bg-white border border-slate-200 text-blue-500 hover:bg-blue-50 hover:border-blue-200 rounded-xl flex items-center justify-center transition-all shadow-sm"
                        title="Ver no Google Maps"
                    >
                         <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.7 3.8C15 .1 9 .1 5.3 3.8c-3.7 3.7-3.7 9.8 0 13.5L12 24l6.7-6.7c3.7-3.7 3.7-9.8 0-13.5zm-6.7 10c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                    </a>
                )}
            </div>
          </div>
        ))}
      </div>

      {/* Botão Carregar Mais - Funciona baseando-se no slice do array */}
      {leads.length > visibleCount && (
        <div className="flex justify-center mb-20">
            <button 
                onClick={handleLoadMore}
                className="group relative px-8 py-4 bg-white border-2 border-slate-200 rounded-full shadow-lg hover:shadow-xl hover:border-blue-500 transition-all active:scale-95"
            >
                <div className="flex items-center gap-3">
                    <div className="bg-slate-100 rounded-full p-2 group-hover:bg-blue-100 transition-colors">
                        <svg className="w-4 h-4 text-slate-600 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
                    </div>
                    <span className="text-xs font-black text-slate-700 uppercase tracking-widest group-hover:text-blue-700">
                        Carregar Mais Resultados ({leads.length - visibleCount} restantes)
                    </span>
                </div>
            </button>
        </div>
      )}

      {leads.length === 0 && !loading && !errorInfo && (
        <div className="py-32 text-center opacity-30">
            <div className="w-24 h-24 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
               <span className="text-4xl grayscale">🗺️</span>
            </div>
            <p className="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500">Aguardando pesquisa</p>
        </div>
      )}
    </div>
  );
};
