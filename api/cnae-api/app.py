"""
API REST para o Dicionário CNAE 2.0
Usa Flask para servir a API e SQLite para o banco de dados.
"""
import os
import sqlite3
import json
from flask import Flask, request, jsonify, g
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# Configuração do banco de dados
DB_PATH = os.environ.get('CNAE_DB_PATH', '/app/data/cnae.db')

def get_db():
    if 'db' not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db

@app.teardown_appcontext
def close_db(error):
    db = g.pop('db', None)
    if db is not None:
        db.close()

# Inicializar banco de dados
def init_db():
    with app.app_context():
        db = get_db()
        db.execute('''CREATE TABLE IF NOT EXISTS dicionario_cnae (
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
        
        # Verificar se já tem dados
        count = db.execute('SELECT COUNT(*) FROM dicionario_cnae').fetchone()[0]
        if count == 0:
            print("Banco de dados vazio. Populando com dados iniciais...")
            # Em produção, você faria o insert aqui ou importaria de um JSON
            # Para este exemplo, vamos carregar do JSON se existir
            json_path = '/app/data/dicionario_cnae.json'
            if os.path.exists(json_path):
                with open(json_path, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    for t in data['termos']:
                        db.execute('''INSERT INTO dicionario_cnae 
                            (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)''',
                            (t.get('codigo_cnae'), t.get('descricao_cnae'), t.get('secao'), 
                             t.get('divisao'), t.get('grupo'), t.get('classe'), 
                             t.get('termo'), t.get('tipo'), t.get('relevancia', 50)))
                db.commit()
                print(f"Importados {len(data['termos'])} registros.")

# Endpoints da API

@app.route('/api/status', methods=['GET'])
def status():
    return jsonify({
        'status': 'ok',
        'message': 'API Dicionário CNAE 2.0 está funcionando'
    })

@app.route('/api/search', methods=['GET'])
def search():
    """Busca por termo no dicionário CNAE"""
    query = request.args.get('q', '').strip()
    tipo = request.args.get('tipo', '')
    secao = request.args.get('secao', '')
    limit = request.args.get('limit', 50, type=int)
    
    if not query:
        return jsonify({'error': 'Parâmetro "q" é obrigatório'}), 400
    
    db = get_db()
    
    # Construir query
    sql = "SELECT * FROM dicionario_cnae WHERE termo LIKE ?"
    params = [f'%{query}%']
    
    if tipo:
        sql += " AND tipo = ?"
        params.append(tipo)
    
    if secao:
        sql += " AND secao = ?"
        params.append(secao)
    
    sql += " ORDER BY relevancia DESC LIMIT ?"
    params.append(limit)
    
    rows = db.execute(sql, params).fetchall()
    
    results = [dict(row) for row in rows]
    
    # Agrupar por CNAE
    grouped = {}
    for r in results:
        cod = r['codigo_cnae']
        if cod not in grouped:
            grouped[cod] = {
                'codigo_cnae': cod,
                'descricao_cnae': r['descricao_cnae'],
                'secao': r['secao'],
                'divisao': r['divisao'],
                'grupo': r['grupo'],
                'classe': r['classe'],
                'termos': []
            }
        grouped[cod]['termos'].append({
            'termo': r['termo'],
            'tipo': r['tipo'],
            'relevancia': r['relevancia']
        })
    
    return jsonify({
        'query': query,
        'total_resultados': len(grouped),
        'resultados': list(grouped.values())
    })

@app.route('/api/cnae/<codigo>', methods=['GET'])
def get_cnae(codigo):
    """Obter detalhes de um CNAE específico"""
    db = get_db()
    rows = db.execute('SELECT * FROM dicionario_cnae WHERE codigo_cnae = ?', (codigo,)).fetchall()
    
    if not rows:
        return jsonify({'error': 'CNAE não encontrado'}), 404
    
    first = dict(rows[0])
    termos = [{'termo': dict(r)['termo'], 'tipo': dict(r)['tipo'], 'relevancia': dict(r)['relevancia']} for r in rows]
    
    return jsonify({
        'codigo_cnae': first['codigo_cnae'],
        'descricao_cnae': first['descricao_cnae'],
        'secao': first['secao'],
        'divisao': first['divisao'],
        'grupo': first['grupo'],
        'classe': first['classe'],
        'termos': termos,
        'total_termos': len(termos)
    })

@app.route('/api/secoes', methods=['GET'])
def get_secoes():
    """Listar todas as seções com contagem"""
    db = get_db()
    rows = db.execute('''SELECT secao, COUNT(*) as total, COUNT(DISTINCT codigo_cnae) as total_cnaes 
                         FROM dicionario_cnae GROUP BY secao ORDER BY secao''').fetchall()
    return jsonify({'secoes': [dict(r) for r in rows]})

if __name__ == '__main__':
    init_db()
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
