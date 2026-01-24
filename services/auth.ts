
import { AppUser, AppTenant } from '../types';

/**
 * Validação de usuário simplificada
 * Permite acesso autenticado ao sistema
 */
export const validateUser = async (email: string): Promise<{ user: AppUser; tenant: AppTenant } | null> => {
  const cleanEmail = email.toLowerCase().trim();
  
  if (!cleanEmail.includes('@')) {
    throw new Error("Por favor, insira um e-mail válido.");
  }

  await new Promise(resolve => setTimeout(resolve, 600));

  console.log(`[Auth] Acesso validado para: ${cleanEmail}`);
  
  return {
    user: {
      id: cleanEmail,
      name: cleanEmail.split('@')[0].split('.')[0].charAt(0).toUpperCase() + cleanEmail.split('@')[0].split('.')[0].slice(1),
      email: cleanEmail,
      tenantId: "999",
      profile: 'admin'
    },
    tenant: {
      id: "999",
      name: 'Empresa Prospectora'
    }
  };
};
