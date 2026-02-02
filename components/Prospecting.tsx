
import React, { useState, useEffect } from 'react';
import { Lead, CRMConfig, CRMContact, SearchHistoryItem, TokenUsage } from '../types';
import { searchLeadsOnMaps } from '../services/searchService';
import { unlockLeads } from '../services/unlockService';
import { sendSingleToCRM } from '../services/api';
import { StorageService } from '../services/storage';

interface ProspectingProps {
  config: CRMConfig;
  initialHistoryItem?: SearchHistoryItem;
  userCoords?: { latitude: number; longitude: number };
  userLocationName?: string;
  onExportToExcel?: () => void;
  /** Limite de tokens: quando limitReached ou conta suspensa, a busca é bloqueada no frontend */
  tokenUsage?: TokenUsage;
  tenantStatus?: string;
  /** Chamado quando a API de busca retorna tokenUsage atualizado (após cada busca) */
  onTokenUsageUpdate?: (tokenUsage: TokenUsage) => void;
}

export const Prospecting: React.FC<ProspectingProps> = ({ config, initialHistoryItem, userCoords, userLocationName, onExportToExcel, tokenUsage, tenantStatus, onTokenUsageUpdate }) => {
  const canSearch = !tokenUsage?.limitReached && tenantStatus !== 'suspended';
  const [query, setQuery] = useState('');
  const [location, setLocation] = useState('');
  const [tag, setTag] = useState('');
  /** Limite enviado à API como maxCrawledPlacesPerSearch (máx. lugares por busca). */
  const [maxPlaces, setMaxPlaces] = useState(20);
  const [useGPS, setUseGPS] = useState(false);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [searchId, setSearchId] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [sendingIndividual, setSendingIndividual] = useState<string | null>(null);
  const [unlockingIds, setUnlockingIds] = useState<string[]>([]);
  const [errorInfo, setErrorInfo] = useState<string | null>(null);
  
  // Controle de Paginação Local
  const [visibleCount, setVisibleCount] = useState(12);

  // Efeito para carregar dados iniciais (Props ou Histórico)
  useEffect(() => {
    if (initialHistoryItem) {
      // Prioridade 1: Veio do clique no "Ver Novamente" do histórico — preservar estado (desbloqueados mantidos)
      loadFromHistoryItem(initialHistoryItem, true);
    } else {
      // Prioridade 2: Carregar a última busca ao abrir/atualizar — exibir como bloqueado (sem sessão de desbloqueio)
      const lastSearch = StorageService.getLastSearch();
      if (lastSearch) {
        loadFromHistoryItem(lastSearch, false);
      }
    }
  }, [initialHistoryItem]);

  const loadFromHistoryItem = (item: SearchHistoryItem, _preserveUnlocked: boolean) => {
      setQuery(item.query);
      setLocation(item.location);
      setTag(item.tag);
      setSearchId(item.id ?? null); // ID da pesquisa no banco — desbloqueio funciona pelo histórico
      if (item.leads && item.leads.length > 0) {
        // Leads vêm do banco (history.php): já vêm com locked true/false e dados quando desbloqueados
        const leadsToShow: Lead[] = item.leads.map((l) => ({
          id: l.id,
          name: l.name ?? '',
          locked: l.locked !== false,
          dbId: l.dbId,
          ...(l.locked === false ? {
            phone: l.phone,
            email: l.email,
            address: l.address,
            website: l.website,
            mapsUri: l.mapsUri,
            cnpj: l.cnpj,
            partners: l.partners,
          } : {}),
        }));
        setLeads(leadsToShow);
      } else {
        setLeads([]);
      }
  };

  // Atualizar o histórico quando os leads mudam (ex.: após desbloqueio), para "Ver novamente" manter desbloqueados
  useEffect(() => {
    if (leads.length === 0 || !query.trim()) return;
    const history = StorageService.getHistory();
    const match = history.find((h) => h.query === query && h.location === location);
    if (match) {
      const updated = history.map((h) => (h.id === match.id ? { ...h, leads } : h));
      StorageService.saveHistory(updated);
    }
  }, [leads, query, location]);

  const performSearch = async () => {
    if (!canSearch) {
      setErrorInfo('Você atingiu o limite de tokens do seu plano para este período. Solicite mais créditos em "Solicitar Créditos" ou aguarde o próximo período.');
      return;
    }
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
      // Busca leads usando API de busca (Google Maps); resultados vêm bloqueados (dados sensíveis criptografados)
      const { leads: results, tokenUsage: newTokenUsage, searchId: newSearchId } = await searchLeadsOnMaps(
        cleanQuery, 
        useGPS ? undefined : cleanLocation, 
        [],
        useGPS ? userCoords : undefined,
        useGPS ? userLocationName : undefined,
        Math.max(1, Math.min(1000, maxPlaces))
      );
      if (newTokenUsage != null && onTokenUsageUpdate) onTokenUsageUpdate(newTokenUsage);
      setSearchId(newSearchId ?? null);

      if (!results || results.length === 0) {
        setErrorInfo(`Nenhum resultado encontrado para "${cleanQuery}" ${useGPS ? (userLocationName ? `em ${userLocationName}` : 'ao seu redor') : `em ${cleanLocation}`}. Tente um termo mais amplo.`);
      } else {
        setLeads(results);

        // Histórico vem do banco (history.php); aqui só atualizamos o cache local com representação bloqueada da busca atual
        const leadsForHistory: Lead[] = results.map((l) => ({
          id: l.id,
          name: l.name ?? '',
          locked: true,
          dbId: l.dbId,
        }));
        const historyItem: SearchHistoryItem = {
            id: Date.now().toString(),
            query: cleanQuery,
            location: useGPS ? (userLocationName || 'Localização GPS') : cleanLocation,
            tag: tag,
            timestamp: new Date().toISOString(),
            resultsCount: results.length,
            leads: leadsForHistory,
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

  const handleUnlockOne = async (leadId: string) => {
    if (!searchId) {
      setErrorInfo('Sessão da pesquisa expirada. Faça uma nova busca.');
      return;
    }
    setUnlockingIds(prev => [...prev, leadId]);
    setErrorInfo(null);
    try {
      const { unlocked, tokenUsage: newTokenUsage } = await unlockLeads(searchId, [leadId]);
      if (newTokenUsage != null && onTokenUsageUpdate) onTokenUsageUpdate(newTokenUsage);
      setLeads(prev => prev.map(l => {
        if (l.id === leadId && unlocked[leadId]) {
          return { ...l, ...unlocked[leadId], locked: false };
        }
        return l;
      }));
    } catch (err: any) {
      setErrorInfo(err.message || 'Erro ao desbloquear');
    } finally {
      setUnlockingIds(prev => prev.filter(id => id !== leadId));
    }
  };

  const handleUnlockPage = async () => {
    const lockedVisible = visibleLeads.filter(l => l.locked).map(l => l.id);
    if (lockedVisible.length === 0) return;
    if (!searchId) {
      setErrorInfo('Sessão da pesquisa expirada. Faça uma nova busca.');
      return;
    }
    setUnlockingIds(lockedVisible);
    setErrorInfo(null);
    try {
      const { unlocked, tokenUsage: newTokenUsage } = await unlockLeads(searchId, lockedVisible);
      if (newTokenUsage != null && onTokenUsageUpdate) onTokenUsageUpdate(newTokenUsage);
      setLeads(prev => prev.map(l => {
        if (l.id in unlocked) {
          return { ...l, ...unlocked[l.id], locked: false };
        }
        return l;
      }));
    } catch (err: any) {
      setErrorInfo(err.message || 'Erro ao desbloquear');
    } finally {
      setUnlockingIds([]);
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
          <div className="md:col-span-3">
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
          <div className="md:col-span-1">
            <label className="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest" title="Enviado à API como maxCrawledPlacesPerSearch">Limite (lugares)</label>
            <input
              type="number"
              min={1}
              max={1000}
              className="w-full px-3 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all"
              placeholder="20"
              value={maxPlaces}
              onChange={(e) => setMaxPlaces(Math.max(1, Math.min(1000, parseInt(String(e.target.value), 10) || 20)))}
            />
          </div>
          <div className="md:col-span-2 flex items-end">
            <button
              onClick={performSearch}
              disabled={loading || !canSearch}
              className="w-full py-3 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-xl shadow-slate-100 disabled:opacity-50 flex items-center justify-center uppercase tracking-wider text-xs"
            >
              {loading ? (
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              ) : !canSearch ? 'Sem créditos disponíveis' : 'Buscar Tudo'}
            </button>
          </div>
        </div>
      </div>

      {!canSearch && (
        <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium flex items-center gap-2">
          <span className="text-lg">⚠️</span>
          <span>Você atingiu o limite de tokens do seu plano para este período. Cada página de resultados (até 20 itens) consome 1 token. Solicite mais créditos em <strong>Solicitar Créditos</strong> no menu ou aguarde o próximo período.</span>
        </div>
      )}

      {errorInfo && (
        <div className="mb-8 p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm animate-fade-in">
          <div className="flex items-center gap-3">
            <span className="text-xl">⚠️</span>
            <p className="text-xs font-bold leading-relaxed">{errorInfo}</p>
          </div>
        </div>
      )}

      {/* Contador de Resultados + Tokens disponíveis + Exportar para Excel */}
      {leads.length > 0 && (
         <div className="mb-6 flex flex-wrap justify-between items-end gap-4 px-2">
            <div>
                <h3 className="text-xl font-black text-slate-900 tracking-tight">Resultados da Busca</h3>
                <p className="text-xs text-slate-500 font-medium">
                   Exibindo <span className="font-bold text-slate-900">{Math.min(visibleCount, leads.length)}</span> de <span className="font-bold text-slate-900">{leads.length}</span> empresas encontradas
                </p>
                <p className="text-xs text-slate-500 font-medium mt-1">
                   Tokens disponíveis na conta: <span className="font-bold text-slate-900">
                     {tokenUsage == null ? '—' : tokenUsage.limit === 0 ? 'Ilimitado' : Math.max(0, tokenUsage.limit - tokenUsage.used)}
                   </span>
                </p>
            </div>
            <div className="flex items-center gap-3">
              {visibleLeads.some(l => l.locked) && searchId && (
                <button
                  type="button"
                  onClick={handleUnlockPage}
                  disabled={unlockingIds.length > 0}
                  className="flex items-center gap-2 px-5 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg disabled:opacity-50"
                  title="Desbloquear todos os resultados visíveis desta página"
                >
                  {unlockingIds.length > 0 ? 'Desbloqueando...' : `Desbloquear página (${visibleLeads.filter(l => l.locked).length})`}
                </button>
              )}
              {onExportToExcel && (
                <button
                  onClick={onExportToExcel}
                  disabled={!searchId || (errorInfo != null && errorInfo.includes('Sessão da pesquisa expirada'))}
                  className="flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg shadow-emerald-900/20 disabled:opacity-50 disabled:cursor-not-allowed"
                  title={searchId ? 'Exportar todas as pesquisas para Excel' : 'Sessão expirada. Faça uma nova busca para exportar.'}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  Exportar para Excel
                </button>
              )}
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
              {lead.locked ? 'Bloqueado' : (lead.partners ? 'Dados Ricos' : 'Lead')}
            </div>

            {/* Conteúdo */}
            <div>
                {/* Nome / Razão Social */}
                <h3 className="font-extrabold text-slate-900 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">
                    {lead.name}
                </h3>

                {lead.locked ? (
                  /* Dados bloqueados: liberar um a um ou pela página (sem débito de token) */
                  <div className="space-y-4 mb-6">
                    <div className="flex items-center gap-3 bg-slate-50 p-3 rounded-lg -mx-2 border border-slate-200">
                      <span className="text-slate-400 text-xs">🔒</span>
                      <span className="text-slate-500 font-bold text-xs">Telefone, email e endereço bloqueados</span>
                    </div>
                    <div className="flex items-center gap-3 bg-slate-50 p-3 rounded-lg -mx-2 border border-slate-200">
                      <span className="text-slate-400 text-xs">📧</span>
                      <span className="text-slate-500 font-bold text-xs">Desbloqueie para visualizar</span>
                    </div>
                    <div className="flex items-center gap-3 bg-slate-50 p-3 rounded-lg -mx-2 border border-slate-200">
                      <span className="text-slate-400 text-xs">📍</span>
                      <span className="text-slate-500 font-bold text-xs">Desbloqueie para ver dados</span>
                    </div>
                    <button
                      type="button"
                      onClick={() => handleUnlockOne(lead.id)}
                      disabled={unlockingIds.includes(lead.id)}
                      className="w-full py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-[10px] uppercase tracking-wide transition-all disabled:opacity-50 flex justify-center items-center gap-2"
                    >
                      {unlockingIds.includes(lead.id) ? 'Desbloqueando...' : 'Desbloquear para ver dados'}
                    </button>
                  </div>
                ) : (
                  <>
                    {/* Dados de Contato (desbloqueados) */}
                    <div className="space-y-3 mb-6">
                        <div className="flex items-center gap-3 bg-emerald-50/50 p-2 rounded-lg -mx-2">
                            <span className="text-emerald-500 text-xs">📞</span>
                            <span className="text-emerald-700 font-bold text-xs">
                                {lead.phone || 'Sem telefone'}
                            </span>
                        </div>

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

                        <div className="flex items-start gap-3 pt-2 border-t border-slate-100">
                            <span className="text-rose-400 text-xs mt-0.5">📍</span>
                            <p className="text-slate-500 font-semibold text-[10px] leading-relaxed line-clamp-2">
                                {lead.address || '—'}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2 mb-6 opacity-60">
                        <span className="bg-slate-100 text-slate-400 text-[8px] font-bold px-2 py-1 rounded uppercase">Fonte 1</span>
                        {lead.sources && lead.sources.length > 1 && (
                            <span className="bg-slate-100 text-slate-400 text-[8px] font-bold px-2 py-1 rounded uppercase">Fonte 2</span>
                        )}
                    </div>
                  </>
                )}
            </div>

            {/* Footer com Botões (só quando desbloqueado para exportar) */}
            <div className="flex items-center gap-3 pt-4 border-t border-slate-100 mt-auto">
                {!lead.locked && (
                  <>
                    <button 
                        onClick={() => handleSendSingle(lead)}
                        disabled={sendingIndividual === lead.id}
                        className="flex-grow bg-white border border-slate-200 text-slate-900 hover:bg-slate-50 hover:border-slate-300 font-black text-[10px] py-3 rounded-xl uppercase tracking-wide transition-all shadow-sm active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2"
                    >
                        {sendingIndividual === lead.id ? 'Enviando...' : 'Exportando'}
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
                  </>
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
