<?php

/**
 * Класс для yandex api
 */
class yapi {

    private $_client_id     = '';
    private $_client_secret = '';
    public $token;

    public function __construct()
    {
        // Есть ли token в cookies
        if (isset($_COOKIE['yapi_token'])) {
            $this->token = $_COOKIE['yapi_token'];
        }
    }

    /**
     * Получение ответа от yandex
     * @param string $url
     * @param array $headers
     * @param array|string $fields
     * @return array
     */
    public function getResponse($url, $headers = array(), $fields = NULL)
    {
        $headers[] = 'Content-type: application/x-www-form-urlencoded';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($fields) {
            if (is_array($fields)) {
                $post_arr = array();
                foreach ($fields as $key => $value) {
                    $post_arr[] = $key . "=" . $value;
                }
                $data = implode('&',$post_arr);
            } else {
                $data = $fields;
            }

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array(
            'code'     => $code,
            'response' => $response
        );

    }

    /**
     * Авторизация
     */
    public function auth($code)
    {
        $ch = curl_init();

        if (!$code) {
            $url = 'https://oauth.yandex.ru/authorize?response_type=code&client_id=' . $this->_client_id;

            header('Location: '. $url);
        } elseif ($this->token) {
            return TRUE;
        } else {
            $fields = array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $this->_client_id,
                'client_secret' => $this->_client_secret
            );

            $result = $this->getResponse('https://oauth.yandex.ru/token', array(), $fields);

            if ($result['code'] == 200) {
                $json = json_decode($result['response']);
                $this->token = $json->access_token;

                // Сохраняем token в cookies
                setcookie('yapi_token', $this->token, time() + 2400);

                return TRUE;
            }

            return FALSE;
        }
    }

    /**
     * Получение сервисного документа
     */
    public function getServiseDocument()
    {
        $url = 'https://webmaster.yandex.ru/api/v2';
        $headers = array('Authorization: OAuth ' . $this->token);
        
        $result = $this->getResponse($url, $headers);
        $xml = new SimpleXMLElement($result['response']);
    }

    /**
     * Добавление оригинального текста
     */
    public function addOriginalText($site_id, $text)
    {
        $text = strip_tags(trim($text));
        $url = 'https://webmaster.yandex.ru/api/v2/hosts/' . $site_id . '/original-texts';

        $str_xml = '<original-text><content>' . $text . '</content></original-text>';

        $headers = array('Authorization: OAuth ' . $this->token, 'Content-Type: application/xml; charset=utf-8', 'Content-Length: ' . strlen($str_xml));
        $result = $this->getResponse($url, $headers, $str_xml);

        if ($result['code'] == 201) {
            //$xml = new SimpleXMLElement($result['response']);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Получение списка оригинальных текстов
     */
    public function getOriginalTexts($site_id)
    {
        $url = 'https://webmaster.yandex.ru/api/v2/hosts/' . $site_id . '/original-texts';
        $headers = array('Authorization: OAuth ' . $this->token);

        $result = $this->getResponse($url, $headers);

        if ($result['code'] == 200) {
            $xml = new SimpleXMLElement($result['response']);
            return $xml;
        }

        return FALSE;
    }


    /**
     * Получение списка сайтов пользователя
     */
    public function getSitesList()
    {
        $url = 'https://webmaster.yandex.ru/api/v2/hosts/';
        $headers = array('Authorization: OAuth ' . $this->token);

        $result = $this->getResponse($url, $headers);

        if ($result['code'] == 200) {
            $xml = new SimpleXMLElement($result['response']);

            $host_xml = $xml->xpath('host');

            $hosts = array();
            foreach ($host_xml as $host) {
                $hosts[] = array(
                    'name'        => (string) $host->name,
                    'href'        => (string) $host->attributes()->href,
                    'site_id'     => str_replace('https://webmaster.yandex.ru/api/v2/hosts/', '' , $host->attributes()->href),
                    'url_count'   => (string) $host->{'url-count'},
                    'index_count' => (string) $host->{'index-count'},
                    'virused'     => (string) $host->virused,
                    'last_access' => (string) $host->{'last-access'},
                    'tcy'         => (string) $host->tcy
                );
            }

            return $hosts;
        }

        return FALSE;
    }

}