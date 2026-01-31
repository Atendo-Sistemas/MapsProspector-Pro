
import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import { Login } from './components/Login';
import { AppUser, AppTenant, TokenUsage } from './types';

const API_BASE = '';

function Root() {
  const [user, setUser] = useState<AppUser | null>(null);
  const [tenant, setTenant] = useState<AppTenant | null>(null);
  const [tokenUsage, setTokenUsage] = useState<TokenUsage | null>(null);
  const [authChecked, setAuthChecked] = useState(false);

  useEffect(() => {
    const checkAuth = async () => {
      try {
        const res = await fetch(`${API_BASE}/api/auth.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'check' }),
          credentials: 'include',
        });
        const data = await res.json();
        if (data.success && data.data?.user && data.data?.tenant) {
          setUser(data.data.user as AppUser);
          setTenant(data.data.tenant as AppTenant);
          setTokenUsage(data.data.tokenUsage ?? null);
        }
      } catch {
        // Sem sessão ou erro de rede
      } finally {
        setAuthChecked(true);
      }
    };
    checkAuth();
  }, []);

  const handleLogin = (data: { user: AppUser; tenant: AppTenant; tokenUsage?: TokenUsage }) => {
    setUser(data.user);
    setTenant(data.tenant);
    setTokenUsage(data.tokenUsage ?? null);
  };

  const handleLogout = async () => {
    try {
      await fetch(`${API_BASE}/api/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }),
        credentials: 'include',
      });
    } catch {
      // Ignora erro de rede
    }
    setUser(null);
    setTenant(null);
    setTokenUsage(null);
  };

  if (!authChecked) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0F172A]">
        <span className="w-8 h-8 border-2 border-white border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!user || !tenant) {
    return <Login onLogin={handleLogin} />;
  }

  return (
    <App
      user={user}
      tenant={tenant}
      tokenUsage={tokenUsage ?? undefined}
      onLogout={handleLogout}
      onTokenUsageUpdate={setTokenUsage}
    />
  );
}

const rootElement = document.getElementById('root');
if (!rootElement) {
  throw new Error('Não foi possível encontrar o elemento raiz para montar a aplicação');
}

const root = ReactDOM.createRoot(rootElement);
root.render(
  <React.StrictMode>
    <Root />
  </React.StrictMode>
);
