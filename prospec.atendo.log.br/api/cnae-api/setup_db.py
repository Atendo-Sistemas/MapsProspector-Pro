"""
Script para popular o banco de dados SQLite com os dados do CNAE.
Execute dentro do container ou na VPS.
"""
import sqlite3
import json
import os

DB_PATH = os.environ.get('CNAE_DB_PATH', '/app/data/cnae.db')
JSON_PATH = os.environ.get('CNAE_JSON_PATH', '/app/data/dicionario_cnae.json')

def setup_db():
    print(f"Conectando ao banco: {DB_PATH}")
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL")
    
    # Criar tabela
    conn.execute('''CREATE TABLE IF NOT EXISTS dicionario_cnae (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo_cnae TEXT NOT NULL,
        descricao_cnae TEXT NOT NULL,
        secao TEXT NOT NULL,
        divisao TEXT NOT NULL,
        grupo TEXT NOT NULL,
        classe TEXT NOT NULL,
        termo TEXT NOT NULL,
        tipo TEXT NOT NULL DEFAULT 'descricao-oficial',
        relevancia INTEGER DEFAULT 50
    )''')
    
    # Criar índices
    conn.execute('CREATE INDEX IF NOT EXISTS idx_codigo_cnae ON dicionario_cnae(codigo_cnae)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_termo ON dicionario_cnae(termo)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_secao ON dicionario_cnae(secao)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_divisao ON dicionario_cnae(divisao)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_tipo ON dicionario_cnae(tipo)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_relevancia ON dicionario_cnae(relevancia DESC)')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_composto ON dicionario_cnae(codigo_cnae, tipo, relevancia)')
    
    # Verificar se já tem dados
    count = conn.execute('SELECT COUNT(*) FROM dicionario_cnae').fetchone()[0]
    print(f"Registros existentes: {count}")
    
    if count == 0 and os.path.exists(JSON_PATH):
        print(f"Carregando dados de: {JSON_PATH}")
        with open(JSON_PATH, 'r', encoding='utf-8') as f:
            data = json.load(f)
            termos = data['termos']
            
            print(f"Importando {len(termos)} registros...")
            
            batch_size = 500
            for i in range(0, len(termos), batch_size):
                batch = termos[i:i+batch_size]
                conn.executemany(
                    '''INSERT INTO dicionario_cnae 
                       (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)''',
                    [(t.get('codigo_cnae',''), t.get('descricao_cnae',''), t.get('secao',''),
                      t.get('divisao',''), t.get('grupo',''), t.get('classe',''),
                      t.get('termo',''), t.get('tipo',''), t.get('relevancia',50)) for t in batch]
                )
                conn.commit()
                print(f"  Importados {i+len(batch)}/{len(termos)}")
            
            conn.commit()
            final_count = conn.execute('SELECT COUNT(*) FROM dicionario_cnae').fetchone()[0]
            print(f"Total de registros importados: {final_count}")
    
    conn.close()
    print("Banco de dados configurado com sucesso!")

if __name__ == '__main__':
    setup_db()
