<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Aluguel de Veículos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, select, button {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        .success {
            color: #28a745;
            background-color: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #007bff;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-alugado {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-disponivel {
            color: #28a745;
            font-weight: bold;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            cursor: pointer;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
        }
        
        .tab.active {
            background-color: #007bff;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <h1>Sistema de Aluguel de Veículos</h1>
    
    <?php
    include 'conexao.php';

    $message = '';
    $messageType = '';
    
    // Processar formulário de aluguel
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'alugar') {
        // Verificar se nome_cliente foi enviado e não está vazio
        $nome_cliente = isset($_POST['nome_cliente']) ? trim($_POST['nome_cliente']) : '';
        $codigov = isset($_POST['codigov']) ? $_POST['codigov'] : '';
        $data_aluguel = isset($_POST['data_aluguel']) ? $_POST['data_aluguel'] : '';
        
        if (empty($nome_cliente)) {
            $message = "Por favor, informe o nome do cliente!";
            $messageType = 'error';
        } elseif (empty($codigov)) {
            $message = "Por favor, selecione um veículo!";
            $messageType = 'error';
        } elseif (empty($data_aluguel)) {
            $message = "Por favor, informe a data de aluguel!";
            $messageType = 'error';
        } else {
            try {
                // Verificar se o cliente já existe
                $stmt = $pdo->prepare("SELECT codigoc FROM cliente WHERE nome = ?");
                $stmt->execute([$nome_cliente]);
                $codigoc = $stmt->fetchColumn();
                
                // Se o cliente não existir, inseri-lo
                if ($codigoc === false) {
                    $stmt = $pdo->prepare("INSERT INTO cliente (codigoc, nome) VALUES (NULL, ?)");
                    $stmt->execute([$nome_cliente]);
                    $codigoc = $pdo->lastInsertId();
                }
                
                // Verificar se o veículo está disponível
                $stmt = $pdo->prepare("SELECT disponivel FROM veiculo WHERE codigov = ?");
                $stmt->execute([$codigov]);
                $disponivel = $stmt->fetchColumn();
                
                if (!$disponivel) {
                    $message = "Este veículo já está alugado!";
                    $messageType = 'error';
                } else {
                    $data_atual = date('Y-m-d');
                    if (strtotime($data_aluguel) > strtotime($data_atual)) {
                        $message = "A data de aluguel não pode ser no futuro!";
                        $messageType = 'error';
                    } else {
                        // Inserir na tabela aluga
                        $stmt = $pdo->prepare("
                            INSERT INTO aluga (codigoc, codigov, data_aluguel, data_devolucao)
                            VALUES (?, ?, ?, NULL)
                        ");
                        $stmt->execute([$codigoc, $codigov, $data_aluguel]);
                        
                        // Atualizar disponibilidade
                        $stmt = $pdo->prepare("UPDATE veiculo SET disponivel = FALSE WHERE codigov = ?");
                        $stmt->execute([$codigov]);
                        
                        $message = "Veículo alugado com sucesso para " . htmlspecialchars($nome_cliente) . "!";
                        $messageType = 'success';
                    }
                }
            } catch(PDOException $e) {
                $message = "Erro ao alugar veículo: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Processar devolução
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'devolver') {
        $codigoc = isset($_POST['codigoc']) ? $_POST['codigoc'] : '';
        $codigov = isset($_POST['codigov']) ? $_POST['codigov'] : '';
        $data_aluguel = isset($_POST['data_aluguel']) ? $_POST['data_aluguel'] : '';
        $data_devolucao = isset($_POST['data_devolucao']) ? $_POST['data_devolucao'] : '';
        
        if (empty($codigoc) || empty($codigov) || empty($data_aluguel)) {
            $message = "Por favor, selecione todas as informações do aluguel!";
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT data_aluguel FROM aluga WHERE codigoc = ? AND codigov = ? AND data_aluguel = ?");
                $stmt->execute([$codigoc, $codigov, $data_aluguel]);
                $data_aluguel_db = $stmt->fetchColumn();
                
                if (!$data_aluguel_db) {
                    $message = "Aluguel não encontrado!";
                    $messageType = 'error';
                } else {
                    if (empty($data_devolucao)) {
                        $message = "Por favor, informe a data de devolução!";
                        $messageType = 'error';
                    } elseif (strtotime($data_devolucao) < strtotime($data_aluguel_db)) {
                        $message = "A data de devolução não pode ser anterior à data de aluguel!";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE aluga SET data_devolucao = ? 
                            WHERE codigoc = ? AND codigov = ? AND data_aluguel = ?
                        ");
                        $stmt->execute([$data_devolucao, $codigoc, $codigov, $data_aluguel]);
                        
                        $stmt = $pdo->prepare("UPDATE veiculo SET disponivel = TRUE WHERE codigov = ?");
                        $stmt->execute([$codigov]);
                        
                        $message = "Veículo devolvido com sucesso!";
                        $messageType = 'success';
                    }
                }
            } catch(PDOException $e) {
                $message = "Erro ao devolver veículo: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Buscar dados para os formulários
    $veiculos = $pdo->query("SELECT * FROM veiculo ORDER BY marca, modelo")->fetchAll();
    $alugueis_ativos = $pdo->query("
        SELECT a.codigoc, a.codigov, a.data_aluguel, c.nome AS cliente_nome, v.marca, v.modelo
        FROM aluga a
        JOIN cliente c ON a.codigoc = c.codigoc
        JOIN veiculo v ON a.codigov = v.codigov
        WHERE a.data_devolucao IS NULL
        ORDER BY a.data_aluguel DESC
    ")->fetchAll();
    
    if ($message) {
        echo "<div class='$messageType'>$message</div>";
    }
    ?>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('alugar')">Alugar Veículo</div>
        <div class="tab" onclick="showTab('devolver')">Devolver Veículo</div>
        <div class="tab" onclick="showTab('relatorio')">Relatório</div>
    </div>
    
    <!-- Formulário de Aluguel -->
    <div id="alugar" class="tab-content active">
        <div class="container">
            <h2>Alugar Veículo</h2>
            <form method="POST">
                <input type="hidden" name="action" value="alugar">
                
                <div class="form-group">
                    <label for="nome_cliente">Nome do Cliente:</label>
                    <input type="text" name="nome_cliente" id="nome_cliente" required>
                </div>
                
                <div class="form-group">
                    <label for="codigov">Veículo:</label>
                    <select name="codigov" id="codigov" required>
                        <option value="">Selecione um veículo</option>
                        <?php foreach($veiculos as $veiculo): ?>
                            <option value="<?= $veiculo['codigov'] ?>" <?= !$veiculo['disponivel'] ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']) ?>
                                <?= !$veiculo['disponivel'] ? ' (ALUGADO)' : ' (DISPONÍVEL)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="data_aluguel">Data do Aluguel:</label>
                    <input type="date" name="data_aluguel" id="data_aluguel" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                
                <button type="submit">Alugar Veículo</button>
            </form>
        </div>
    </div>
    
    <!-- Formulário de Devolução -->
    <div id="devolver" class="tab-content">
        <div class="container">
            <h2>Devolver Veículo</h2>
            <?php if (count($alugueis_ativos) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="devolver">
                    
                    <div class="form-group">
                        <label for="codigoc">Cliente:</label>
                        <select name="codigoc" id="codigoc" required>
                            <option value="">Selecione um cliente</option>
                            <?php foreach($alugueis_ativos as $aluguel): ?>
                                <option value="<?= $aluguel['codigoc'] ?>">
                                    <?= htmlspecialchars($aluguel['cliente_nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="codigov">Veículo:</label>
                        <select name="codigov" id="codigov" required>
                            <option value="">Selecione um veículo</option>
                            <?php foreach($alugueis_ativos as $aluguel): ?>
                                <option value="<?= $aluguel['codigov'] ?>">
                                    <?= htmlspecialchars($aluguel['marca'] . ' ' . $veiculo['modelo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_aluguel">Data do Aluguel:</label>
                        <select name="data_aluguel" id="data_aluguel" required>
                            <option value="">Selecione a data</option>
                            <?php foreach($alugueis_ativos as $aluguel): ?>
                                <option value="<?= $aluguel['data_aluguel'] ?>">
                                    <?= date('d/m/Y', strtotime($aluguel['data_aluguel'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_devolucao">Data da Devolução:</label>
                        <input type="date" name="data_devolucao" id="data_devolucao" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-1 year')) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <button type="submit">Devolver Veículo</button>
                </form>
            <?php else: ?>
                <p>Não há veículos alugados no momento.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Relatório -->
    <div id="relatorio" class="tab-content">
        <div class="container">
            <h2>Relatório de Aluguéis</h2>
            <?php
            $relatorio = $pdo->query("
                SELECT 
                    c.nome AS cliente_nome,
                    v.marca,
                    v.modelo,
                    a.data_aluguel,
                    a.data_devolucao,
                    CASE 
                        WHEN a.data_devolucao IS NULL THEN 'ALUGADO'
                        ELSE 'DEVOLVIDO'
                    END AS status
                FROM aluga a
                JOIN cliente c ON a.codigoc = c.codigoc
                JOIN veiculo v ON a.codigov = v.codigov
                ORDER BY a.data_aluguel DESC
            ")->fetchAll();
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data Aluguel</th>
                        <th>Data Devolução</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($relatorio) > 0): ?>
                        <?php foreach($relatorio as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['cliente_nome']) ?></td>
                                <td><?= htmlspecialchars($item['marca'] . ' ' . $item['modelo']) ?></td>
                                <td><?= date('d/m/Y', strtotime($item['data_aluguel'])) ?></td>
                                <td>
                                    <?= $item['data_devolucao'] ? date('d/m/Y', strtotime($item['data_devolucao'])) : '-' ?>
                                </td>
                                <td>
                                    <span class="<?= $item['status'] === 'ALUGADO' ? 'status-alugado' : 'status-disponivel' ?>">
                                        <?= $item['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">Nenhum aluguel encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>