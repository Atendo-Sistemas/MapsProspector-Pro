
import React, { useState, useEffect } from 'react';
import { Lead, CRMConfig, CRMContact, SearchHistoryItem } from '../types';
import { searchLeadsOnMaps } from '../services/gemini';
import { sendSingleToCRM, sendBatchToCRM } from '../services/api';

interface ProspectingProps {
  config: CRMConfig;
  initialQuery?: { query: string; location: string; tag: string };
  userCoords?: { latitude: number; longitude: number };
}

export const Prospecting: React.FC<ProspectingProps> = ({ config, initialQuery, userCoords }) => {
  const [query, setQuery] = useState(initialQuery?.query || '');
  const [location, setLocation] = useState(initialQuery?.location || '');
  const [tag, setTag] = useState(initialQuery?.tag || '');
  const [useGPS, setUseGPS] = useState(false);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(false);
  const [sendingIndividual, setSendingIndividual] = useState<string | null>(null);
  const [errorInfo, setErrorInfo] = useState<string | null>(null);

  useEffect(() => {
    if (initialQuery) {
      setQuery(initialQuery.query);
      setLocation(initialQuery.location);
      setTag(initialQuery.tag);
    }
  }, [initialQuery]);

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

    try {
      // Passamos undefined no userCoords se não estiver usando GPS, para forçar a lógica de texto
      const results = await searchLeadsOnMaps(
        cleanQuery, 
        useGPS ? undefined : cleanLocation, 
        [], 
        config.selectedModel || 'gemini-2.5-flash',
        useGPS ? userCoords : undefined
      );
      
      if (!results || results.length === 0) {
        setErrorInfo(`Nenhum resultado encontrado para "${cleanQuery}" ${useGPS ? 'ao seu redor' : `em ${cleanLocation}`}. Tente um termo mais amplo.`);
      } else {
        setLeads(results);
      }
    } catch (err: any) {
      // Remove caracteres JSON estranhos se vazarem
      let cleanMsg = err.message;
      if (cleanMsg.includes('{') && cleanMsg.includes('}')) {
         try {
            const parsed = JSON.parse(cleanMsg);
            cleanMsg = parsed.message || cleanMsg;
         } catch(e) {}
      }
      setErrorInfo(cleanMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleSendSingle = async (lead: Lead) => {
    setSendingIndividual(lead.id);
    try {
      const contact: CRMContact = {
        name: lead.name,
        number: lead.phone || "",
        email: lead.email || "",
        tag: tag || "prospect_maps",
        commentary: `Lead de ${location || 'GPS'}. Endereço: ${lead.address}`,
        extraInfo: [
            { name: 'Endereço', value: lead.address },
            { name: 'Maps', value: lead.mapsUri || '' },
            { name: 'Site', value: lead.website || '' }
        ]
      };
      await sendSingleToCRM(config, contact);
      alert('Enviado com sucesso para o CRM!');
    } catch (err: any) {
      alert(`Erro no Envio: ${err.message}`);
    } finally {
      setSendingIndividual(null);
    }
  };

  return (
    <div className="max-w-7xl mx-auto">
      <div className="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-8">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div className="md:col-span-4">
            <label className="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca? (Ex: Petshop)</label>
            <input
              className="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all"
              placeholder="Ex: Clínicas, Lojas..."
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && performSearch()}
            />
          </div>
          <div className="md:col-span-4">
            <div className="flex justify-between items-center mb-1">
              <label className="block text-[10px] font-black text-slate-400 uppercase ml-1 tracking-widest">Onde? (Ex: Olímpia)</label>
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
              <div className="w-full px-5 py-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-bold flex items-center gap-2">
                <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                Buscando ao seu redor
              </div>
            )}
          </div>
          <div className="md:col-span-2">
            <label className="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Etiqueta (Tag)</label>
            <input
              className="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm"
              placeholder="Ex: leads_2024"
              value={tag}
              onChange={(e) => setTag(e.target.value)}
            />
          </div>
          <div className="md:col-span-2 flex items-end">
            <button
              onClick={performSearch}
              disabled={loading}
              className="w-full py-3 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-xl shadow-slate-100 disabled:opacity-50 flex items-center justify-center"
            >
              {loading ? (
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              ) : 'PESQUISAR'}
            </button>
          </div>
        </div>
      </div>

      {errorInfo && (
        <div className="mb-8 p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm">
          <div className="flex items-center gap-3">
            <span className="text-xl">⚠️</span>
            <div>
              <p className="text-[11px] font-black uppercase tracking-widest mb-1">Resultado da Busca</p>
              <p className="text-xs font-bold leading-relaxed">{errorInfo}</p>
            </div>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        {leads.map((lead) => (
          <div key={lead.id} className="bg-white rounded-[2rem] border border-slate-200 overflow-hidden flex flex-col group hover:shadow-2xl hover:shadow-blue-900/5 hover:border-blue-200 transition-all duration-300">
            <div className="p-6 flex-grow">
              <h3 className="font-black text-slate-900 text-sm mb-3 line-clamp-2 uppercase leading-snug">{lead.name}</h3>
              <div className="space-y-2 text-[10px] font-bold">
                <p className="text-emerald-600 flex items-center gap-2">
                  <span className="bg-emerald-100 p-1 rounded-md">📞</span> 
                  {lead.phone || 'Nenhum telefone'}
                </p>
                <p className="text-slate-500 flex items-start gap-2 leading-relaxed">
                  <span className="bg-slate-100 p-1 rounded-md">📍</span> 
                  <span className="line-clamp-2">{lead.address}</span>
                </p>
                {lead.email && (
                  <p className="text-blue-500 flex items-center gap-2">
                    <span className="bg-blue-100 p-1 rounded-md">✉️</span> 
                    {lead.email}
                  </p>
                )}
                {lead.website && (
                  <p className="text-slate-400 flex items-center gap-2 truncate">
                    <span className="bg-slate-100 p-1 rounded-md">🌐</span>
                    {lead.website.replace('https://','').replace('www.','').substring(0,25)}...
                  </p>
                )}
              </div>
            </div>
            <div className="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
              <button 
                onClick={() => handleSendSingle(lead)}
                disabled={sendingIndividual === lead.id}
                className="flex-grow py-3 bg-white border border-slate-200 hover:bg-blue-600 hover:text-white hover:border-blue-600 rounded-xl font-black text-[10px] uppercase transition-all shadow-sm active:scale-95"
              >
                {sendingIndividual === lead.id ? 'ENVIANDO...' : 'EXPORTAR'}
              </button>
              {lead.mapsUri && (
                <a 
                  href={lead.mapsUri} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-xl hover:bg-blue-50 transition-colors shadow-sm"
                >
                  🗺️
                </a>
              )}
            </div>
          </div>
        ))}
      </div>

      {leads.length === 0 && !loading && !errorInfo && (
        <div className="py-32 text-center opacity-40">
            <div className="w-20 h-20 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-4 border border-slate-200 rotate-6">
               <span className="text-3xl">🔍</span>
            </div>
            <p className="text-[10px] font-black uppercase tracking-[0.4em]">Inicie sua prospecção</p>
        </div>
      )}
    </div>
  );
};
