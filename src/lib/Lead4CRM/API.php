<?php
/**
 * Created by PhpStorm.
 * User: arsen
 * Date: 05/12/15
 * Time: 20:54
 */

namespace Lead4CRM;


use Longman\TelegramBot\Telegram;
use Zelenin\SmsRu\Auth\ApiIdAuth;
use Zelenin\SmsRu\Entity\Sms;

class API
{
    /**
     * @var \PDO $db
     */
    protected $db;
    /**
     * @var string $dns Cхема подключения.
     * @var object $conf Объект с настройками системы, содержащий различные переменные и хранящий логины и пароли.
     */
    private $dsn, $conf;

    /**
     * API constructor.
     * @param object $conf Объект с настройками системы, содержащий различные переменные и хранящий логины и пароли.
     */
    public function __construct($conf)
    {
        if ($conf->db->type == 'postgres')
        {
            $this->dsn = "pgsql:host=".$conf->db->host.";dbname=".$conf->db->database;
            try {
                $this->db = new \PDO($this->dsn, $conf->db->username, $conf->db->password);
                $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->db->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
                $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $this->exception($e);
            }
        }
        $this->conf = $conf;
    }

    /**
     * Функция заказа обратного вызова через оператора SIPNET.
     *
     * @param string $phone Номер телефона абонента.
     * @param int $delay Время задержки в секундах.
     * @return string JSON-строка ответа сервера sipnet.ru
     */
    public function getWebCall($phone, $delay = 0)
    {
        $sipnet_url = "https://api.sipnet.ru/cgi-bin/Exchange.dll/sip_balance".
            "?operation=genCall".
            "&sipuid=".$this->conf->sipnet->id.
            "&password=".$this->conf->sipnet->password.
            "&SrcPhone=".$this->conf->sipnet->phone.
            "&DstPhone=".$phone.
            "&Delay=".$delay.
            "&format=2".
            "&lang=ru";
        return file_get_contents($sipnet_url);
    }

    /**
     * Функция получает список CRM систем и передает данные в многомерном массиве.
     *
     * @return array Массив со списком разрешенных к публикации CRM систем.
     */
    public function getCRM()
    {
        $crm_list = array();
        $sql = 'SELECT id, name, dev FROM crm_systems WHERE enabled = TRUE ORDER BY name ASC';
        $systems = $this->getMultipleRows($sql, array());
        foreach ($systems as $system) {
            $crm_list[$system['id']]['id'] = $system['id'];
            $crm_list[$system['id']]['name'] = $system['name'];
            $crm_list[$system['id']]['dev'] = ($system['dev'] == 't') ? true : false;
            $sql = "SELECT id, version FROM crm_versions WHERE crmid = :crmid ORDER BY version ASC";
            $params = array();
            $params[] = array(':crmid', $system['id'], \PDO::PARAM_INT);
            $versions = $this->getMultipleRows($sql, $params);
            $crm = array();
            foreach ($versions as $version) {
                $crm[$version['id']]['id'] = $version['id'];
                $crm[$version['id']]['version'] = $version['version'];
            }
            $crm_list[$system['id']]['versions'] = $crm;
        }
        return $crm_list;
    }

    /**
     * Функция для работы с помойщиком.
     *
     * @param int $crmid Идентификатор CRM системы.
     * @param int $step Номер шага помойщика.
     * @return array Массив с данными для конкретного шага помойщика.
     */
    public function wizard($crmid, $step)
    {
        $return = array(
            'error' => 500,
            'message' => 'Отсутствует обязательный параметр.'
        );
        if ($crmid && $step) {
            if ($step == 2) {
                $sql = "SELECT crm_versions.module, crm_versions.ii, crm_systems.name FROM crm_versions LEFT JOIN crm_systems ON crm_versions.crmid = crm_systems.id WHERE crm_versions.id = :crmid";
                $params = array();
                $params[] = array(':crmid', $crmid, \PDO::PARAM_INT);
                $module = $this->getSingleRow($sql, $params);
                if ($module['module']) {
                    $return = array(
                        'error' => '0',
                        'module' => $module['module'],
                        'name' => $module['name'],
                    );
                } else {
                    $return = array(
                        'error' => '0',
                        'module' => '',
                        'ii' => $module['ii'],
                    );
                }
            }
        }
        return $return;
    }

    /**
     * Проверяет встроенную интеграцию на подключение к внешней CRM системе. И в случае удачи возвращает
     * параметры интеграции для указанной CRM системы.
     *
     * @param string $crm Название CRM системы согласно классификатору API.
     * @param int $uid Идентификатор пользователя.
     * @return array Возвращает параметры встроенной интеграции для конкретной CRM.
     */
    public function getIntegrated($crm, $uid = 0)
    {
        $return = array(
            'error' => 500,
            'message' => 'Данная система не поддерживает встроенную интеграцию.'
        );
        if ($crm == 'megaplan') {
            $sql = "SELECT megaplan FROM users WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $crmid = $this->getSingleRow($sql, $params);
            $opt = array('error' => 0, 'connected' => false);
            if ($crmid['megaplan']) {
                $megaplan = new \megaplan($crmid['megaplan']);
                $opt = array(
                    'error' => 0,
                    'connected' => true,
                    'employees' => $megaplan->getEmployee(),
                    'leadUser' => $megaplan->getLeadUser(),
                );
                $responsibles = $megaplan->getResponsibles();
                foreach ($responsibles as $responsible) {
                    $opt['responsibles'][$responsible] = 'checked';
                }
            }
            $return = $opt;
        }
        return $return;
    }

    /**
     * Функция записи компании в справочник внешней CRM системы. Выполняет запись одной компании за раз.
     *
     * @param string $crm Название CRM системы согласно классификатору API.
     * @param int $uid Идентификатор пользователя.
     * @param array $opt Опции для передачи в класс CRM для сохранения в удаленной CRM.
     * @return string Данные в формате JSON.
     */
    public function crmPostCompany($crm, $uid = 0, array $opt)
    {
        $return = '';
        if ($crm == 'megaplan') {
            $sql = "SELECT megaplan FROM users WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $crmid = $this->getSingleRow($sql, $params);
            if ($crmid['megaplan']) {
                $megaplan = new \megaplan($crmid['megaplan']);
                $return = $megaplan->putCompany($opt);
            }
        }
        return $return;
    }

    /**
     * Функция выполняет сохранение параметров для конкретной CRM.
     *
     * @param string $crm Название CRM системы согласно классификатору API.
     * @param int $uid Идентификатор пользователя.
     * @return bool Возвращает результат сохранения.
     */
    public function crmSaveSettings($crm, $uid = 0)
    {
        $return = false;
        if ($crm == 'megaplan') {
            $sql = "SELECT megaplan FROM users WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $crmid = $this->getSingleRow($sql, $params);
            if ($crmid['megaplan']) {
                $megaplan = new \megaplan($crmid['megaplan']);
                $return = $megaplan->putSetting();
            }
        }
        return $return;
    }

    /**
     * Функция подключения удаленной CRM системы к Lead4CRM.
     *
     * @param string $crm Название CRM системы согласно классификатору API.
     * @return string В случает ошибки возвращает сообщение.
     */
    public function crmConnect($crm)
    {
        $return = '';
        if ($crm == 'megaplan') {
            $auth = \megaplan::Authorize();
            if ($auth === false)
                $return = 'Для данного домена логин/пароль не верный.';
        }
        return $return;
    }

    /**
     * @param string $crm Название CRM системы согласно классификатору API.
     * @param int $uid Идентификатор пользователя.
     * @return bool Возвращает результат отключения.
     */
    public function crmDisconnect($crm, $uid = 0)
    {
        $return = false;
        if ($crm == 'megaplan') {
            $sql = "SELECT megaplan FROM users WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $crmid = $this->getSingleRow($sql, $params);
            if ($crmid['megaplan']) {
                $megaplan = new \megaplan($crmid['megaplan']);
                $return = $megaplan->Disconnect();
            }
        }
        return $return;
    }

    /**
     * @return mixed Возвращает название города в котором находится пользователь.
     */
    static public function getUserCityByIP()
    {
        $ipaddress = self::getRealIpAddr();
        $geoDataJSON = file_get_contents('http://api.sypexgeo.net/json/'.$ipaddress);
        $geoData = json_decode($geoDataJSON);
        return $geoData->city->name_ru;
    }

    /**
     * Функция стороит многомерный массив который состоит из стран и городов, в котором выделен город пользователя.
     *
     * @param string $userCityName Название город пользователя.
     * @return array Возвращает массив стран и городов с возможной отметкой пользовательского города.
     */
    public function getCountries($userCityName)
    {
        $arrCountries = array();
        $sql = "SELECT id, name FROM country ORDER BY sort ASC, name ASC";
        $countries = $this->getMultipleRows($sql, array());
        foreach ($countries as $country) {
            $arrCountries[$country['id']]['id'] = $country['id'];
            $arrCountries[$country['id']]['name'] = $country['name'];
            $sql = "SELECT id, name, parent_id FROM cities WHERE country_id = :cid ORDER BY name ASC";
            $param = array();
            $param[] = array(':cid', $country['id'], \PDO::PARAM_INT);
            $cities = $this->getMultipleRows($sql, $param);
            $arrCities = array();
            foreach ($cities as $city) {
                if ($city['parent_id']) {
                    $arrCities[$city['parent_id']]['children'] = $arrCities[$city['parent_id']]['children'] ? $arrCities[$city['parent_id']]['children'] . ', ' . $city['name'] : $city['name'];
                    if ($userCityName == $city['name'])
                        $arrCities[$city['parent_id']]['selected'] = 1;
                } else {
                    $arrCities[$city['id']]['code'] = $city['id'];
                    $arrCities[$city['id']]['name'] = $city['name'];
                    if ($userCityName == $city['name'])
                        $arrCities[$city['id']]['selected'] = 1;
                }
            }
            $arrCountries[$country['id']]['cities'] = $arrCities;
        }
        return $arrCountries;
    }

    /**
     * Функция стороит массив с данными для запроса авторизации у oAuth провайдеров.
     * Поддерживает следующих oAuth провайдеров:
     * - Вконтакте
     * - Одноклассники
     * - Facebook
     * - Google (Google+)
     * - Mail.Ru
     * - Яндекс
     *
     * @return array Возвращает массив с данными по поддерживаемым oAuth провайдерам.
     */
    public function getOAuthLoginURL()
    {
        if ($_SESSION['state']) {
            $state = $_SESSION['state'];
        } else {
            $state = sha1($_SERVER['HTTP_USER_AGENT'].time());
            $_SESSION['state'] = $state;
        }

        $vk = http_build_query(
            array(
                'client_id'     => $this->conf->provider->vkontakte->CLIENT_ID,
                'scope'         => 'email',
                'redirect_uri'  => 'https://'.$_SERVER['SERVER_NAME'].'/login/vkontakte/',
                'response_type' => 'code',
                'v'             => '5.29',
                'state'         => $state,
                'display'       => 'page',
            )
        );
        $ok = http_build_query(
            array(
                'client_id'     => $this->conf->provider->odnoklassniki->CLIENT_ID,
                'scope'         => 'GET_EMAIL',
                'redirect_uri'  => 'https://'.$_SERVER['SERVER_NAME'].'/login/odnoklassniki/',
                'response_type' => 'code',
                'state'         => $state,
            )
        );
        $fb = http_build_query(
            array(
                'client_id'     => $this->conf->provider->facebook->CLIENT_ID,
                'scope'         => 'email',
                'redirect_uri'  => 'https://'.$_SERVER['SERVER_NAME'].'/login/facebook/',
                'response_type' => 'code',
            )
        );
        $gp = http_build_query(
            array(
                'client_id'              => $this->conf->provider->{"google-plus"}->CLIENT_ID,
                'scope'                  => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
                'redirect_uri'           => 'https://'.$_SERVER['SERVER_NAME'].'/login/google-plus/',
                'response_type'          => 'code',
                'state'                  => $state,
                'access_type'            => 'online',
                'approval_prompt'        => 'auto',
                'login_hint'             => 'email',
                'include_granted_scopes' => 'true',
            )
        );
        $mr = http_build_query(
            array(
                'client_id'     => $this->conf->provider->mailru->CLIENT_ID,
                'redirect_uri'  => 'https://'.$_SERVER['SERVER_NAME'].'/login/mailru/',
                'response_type' => 'code',
            )
        );
        $ya = http_build_query(
            array(
                'client_id'     => $this->conf->provider->yandex->CLIENT_ID,
                'response_type' => 'code',
                'state'         => $state,
            )
        );
        return array(
            'vklogin' => 'https://oauth.vk.com/authorize?' . $vk,
            'oklogin' => 'http://www.odnoklassniki.ru/oauth/authorize?' . $ok,
            'fblogin' => 'https://www.facebook.com/dialog/oauth?' . $fb,
            'gplogin' => 'https://accounts.google.com/o/oauth2/auth?' . $gp,
            'mrlogin' => 'https://connect.mail.ru/oauth/authorize?' . $mr,
            'yalogin' => 'https://oauth.yandex.ru/authorize?' . $ya,
        );
    }

    /**
     * Функция возвращает массив со ссылками меню.
     *
     * @return array
     */
    public function getMenuUrl()
    {
        return array(
            'mainpage_url'      => 'https://' . $_SERVER['SERVER_NAME'],
            'aboutproject_url'  => 'https://' . $_SERVER['SERVER_NAME'] . '/about-project/',
            'aboutours_url'     => 'https://' . $_SERVER['SERVER_NAME'] . '/about-us/',
            'prices_url'        => 'https://' . $_SERVER['SERVER_NAME'] . '/price/',
            'support_url'       => 'https://' . $_SERVER['SERVER_NAME'] . '/support/',
            'blog_url'          => 'http://blog.lead4crm.ru/',
            'cabinet_url'       => 'https://' . $_SERVER['SERVER_NAME'] . '/cabinet/',
            'terms_url'         => 'https://' . $_SERVER['SERVER_NAME'] . '/terms/',
        );
    }

    /**
     * Функция-коллектор авторизации в системе Lead4CRM.
     *
     * @param string $provider Название oAuth провайдера.
     */
    public function getDataLogin($provider)
    {
        switch($provider) {
            case 'facebook':
                $this->getFacebookLogin();
                break;

            case 'vkontakte':
                $this->getVkontakteLogin();
                break;

            case 'odnoklassniki':
                $this->getOdnoklassnikiLogin();
                break;

            case 'google-plus':
                $this->getGoogleLogin();
                break;

            case 'yandex':
                $this->getYandexLogin();
                break;

            case 'mailru':
                $this->getMailRuLogin();
                break;

            default:
                header("HTTP/1.1 412 Precondition Failed");
                header("Content-Type: text/plain");
                echo "Необходимо указать корректный тип авторизации.";
                break;
        }
    }

    /**
     * Функция устанавливает текущее положение о принятии публичной оферты пользователем системы.
     *
     * @param bool $decision
     * @param int $uid
     * @return mixed
     */
    public function getContract($decision, $uid)
    {
        $sql = "UPDATE users SET contract2 = :decision WHERE id = :uid RETURNING contract2";
        $params = array();
        $params[] = array(':decision', $decision, \PDO::PARAM_BOOL);
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $r = $this->getSingleRow($sql, $params);
        $_SESSION['contract'] = $r['contract2'];
        return $_SESSION['contact'];
    }

    /**
     * Функция получает данные (название тарифа и кол-во запросов) о пользователе по ключу доступа и возвращет
     * именнованный массив с данными.
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @return array
     */
    public function getUserDataByAPI($apikey)
    {
        $sql = "SELECT t1.qty2 + trunc((SELECT sum(t2.debet) - sum(t2.credit) FROM log t2 WHERE t2.uid = t1.id) / t3.price) AS qty, t3.name AS tariff FROM users t1 LEFT JOIN tariff t3 ON t1.tariffid2 = t3.id WHERE t1.apikey = :apikey";
        $params = array();
        $params[] = array(':apikey', $apikey, \PDO::PARAM_STR);
        return $this->getSingleRow($sql, $params);
    }

    /**
     * Функция получает данные (баланс, название тарифа и кол-во запросов) о пользвателе по идентификатору пользователя.
     *
     * @param int $uid Идентификатор пользователя.
     * @param bool|true $json Если параметр TRUE то выводиться будет кодированная JSON-строка.
     * @return array Возвращает массив с данными о пользователе.
     */
    public function getUserDataByUID($uid, $json = false)
    {
        $sql = "SELECT (SELECT sum(t2.debet) - sum(t2.credit) FROM log t2 WHERE t2.uid = t1.id) AS balance, t1.qty2 + trunc((SELECT sum(t2.debet) - sum(t2.credit) FROM log t2 WHERE t2.uid = t1.id) / t3.price) AS qty, t3.name AS tariff FROM users t1 LEFT JOIN tariff t3 ON t1.tariffid2 = t3.id WHERE t1.id = :uid";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $user = $this->getSingleRow($sql, $params);
        if ($json) {
            header("Content-Type: application/json");
            echo json_encode($user);
        }
        return $user;
    }

    /**
     * Функция получает все тарифы для текущего пользователя согласно его сумме на балансе, т.е. в данный массив не
     * попадут тарифы стоимость которых будет больше суммы баланса пользователя.
     *
     * @param int $uid Идентификатор пользователя.
     * @return array Массив тарифов для текущего пользователя
     */
    public function getUserTariffList($uid)
    {
        $sql = "SELECT * FROM tariff WHERE domain = 'lead4crm.ru' AND sum <= (SELECT (sum(debet) - sum(credit)) FROM log WHERE uid = :uid) ORDER BY sum ASC";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $tariffs = $this->getMultipleRows($sql, $params);
        $_result = array();
        foreach ($tariffs as $tariff) {
            $_result[$tariff['id']]['name'] = $tariff['name'];
            $_result[$tariff['id']]['code'] = $tariff['code'];
            $_result[$tariff['id']]['price'] = $tariff['price'];
            $_result[$tariff['id']]['sum'] = number_format($tariff['sum'], 2, '.', ' ');
        }
        return $_result;
    }

    /**
     * Функция меняет тарифный план пользователя и отправляет уведомления по всем включенным каналам пользователя.
     *
     * @param string $tariff Символьный код тарифа
     * @param int $uid Идентификатор пользователя.
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function setUserTariff($tariff, $uid)
    {
        $tg = new Telegram($this->conf->telegram->api, $this->conf->telegram->name);
        if ($tariff != 'demo') {
            $sql = "UPDATE users SET tariffid2 = (SELECT id FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru'), qty2 = qty2 + (SELECT queries FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru') WHERE id = :uid AND (SELECT (sum(debet) - sum(credit)) FROM log WHERE uid = :uid) >= (SELECT sum FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru') RETURNING id";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $params[] = array(':tariff', $tariff, \PDO::PARAM_STR);
            $user = $this->getSingleRow($sql, $params);
            if ($user['id'] == $uid) {
                $sql = "INSERT INTO log (uid, credit, credit) VALUES (:uid, (SELECT sum FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru'), 'Активация тарифа ' || (SELECT name FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru'))";
                $this->postSqlQuery($sql, $params);

                // Отправляем уведомления по всем включенным у пользователя каналам
                $sql = "SELECT telegram_chat_id, telegram_balans, icq_uin, icq_balans, sms_phone, sms_balans, wa_phone, wa_balans, (SELECT sum FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru') AS tariff_price, (SELECT name FROM tariff WHERE code = :tariff AND domain = 'lead4crm.ru') AS tariff_name FROM users WHERE id = :uid";
                $nr = $this->getSingleRow($sql, $params);
                if ($nr['telegram_balans'] == 't')
                    $tg->sendNotification("С лицевого счета списана сумма: {$nr['tariff_price']} руб. в счет тарифного плана «{$nr['tariff_name']}»", $nr['telegram_chat_id']);
                if ($nr['icq_balans'] == 't')
                    $this->sendICQ('sendMsg', $nr['icq_uin'], "С лицевого счета списана сумма:\r\t{$nr['tariff_price']} рублей\rВ счет тарифного плана «{$nr['tariff_name']}»");
                if ($nr['sms_balans'] == 't')
                    $this->sendSMS('sendMsg', $nr['sms_phone'], $uid, "С лицевого счета списана сумма: {$nr['tariff_price']} руб. в счет тарифного плана «{$nr['tariff_name']}»");
            }
        }
    }

    /**
     * Функция внесения данных в реферальную таблицу и отправки уведомления на почту администратора о новом партнере.
     *
     * @param array $data Массив данных для добавления в реферальную таблицу.
     * @return bool Возвращает TRUE, если письмо было принято для передачи, иначе FALSE.
     */
    public function setUserReferal($data)
    {
        $sql = "INSERT INTO crm_referals (uid, firm, inn, bik, rs, ks, bank) VALUES (:uid, :firm, :inn, :bik, :rs, :ks, :bank)";
        $params = array();
        $params[] = array(':uid', $data['uid'], \PDO::PARAM_INT);
        $params[] = array(':firm', $data['firm'], \PDO::PARAM_STR);
        $params[] = array(':inn', $data['inn'], \PDO::PARAM_STR);
        $params[] = array(':bik', $data['bik'], \PDO::PARAM_STR);
        $params[] = array(':rs', $data['rs'], \PDO::PARAM_STR);
        $params[] = array(':ks', $data['ks'], \PDO::PARAM_STR);
        $params[] = array(':bank', $data['bank'], \PDO::PARAM_STR);
        $this->postSqlQuery($sql, $params);
        $msg = "Бобрый день!\r\n\r\nКакой-то инициативный решил подзаработать денежек на нашем сервисе, оставил заявочку для реферальной программы, необходимо рассмотреть и одобрить, если все ок, либо написать сообщение о необходимости уточнения или исправления данных.\r\n\r\nИдентификатор пользователя:\t{$data['uid']}\r\nНаименование организации:\t{$data['firm']}\r\nИНН:\t\t\t\t\t\t{$data['inn']}\r\nБИК:\t\t\t\t\t\t{$data['bik']}\r\nР/с:\t\t\t\t\t\t\t{$data['rs']}\r\n\r\nВсе заявки и данные по пользователям доступны в личном кабинете администратора, поэтому рассказывать особо не чего, вот ссылка: https://www.lead4crm.ru/cabinet/\r\n\r\nС уважением,\r\nмегабот сервиса Lead4CRM.";
        $subject = "Lead4CRM: Заявка на реферал";
        $headers = "From: Lead4CRM <noreply@lead4crm.ru>\r\n";
        $headers.= "Reply-To: support@lead4crm.ru\r\n";
        $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
        return mail('arsen@lead4crm.ru', $subject, $msg, $headers);
    }

    /**
     * Функция выполняет обновление таблицы рефералов.
     *
     * @param int $uid Идентификатор пользователя.
     * @param string $field Название поля, которое необходимо обновить в таблице рефералов.
     * @param mixed $data Данные для поля $field.
     * @param string $type Тип данных $data для поля $field.
     * @return bool Всегда возвращает TRUE, вот такой бред, хотя должна быть проверка на изменение информации.
     */
    public function updateUserReferal($uid, $field, $data, $type = "string")
    {
        $sql = "UPDATE crm_referals SET {$field} = :data WHERE uid = :uid";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        if ($type == "string")
            $params[] = array(':data', $data, \PDO::PARAM_STR);
        elseif ($type == "bool")
            $params[] = array(':data', $data, \PDO::PARAM_BOOL);
        else
            $params[] = array(':data', $data, \PDO::PARAM_INT);
        $this->postSqlQuery($sql, $params);
        return true;
    }

    /**
     * Функция добавляем новый URL реферера.
     *
     * @param string $url Интернет адрес ресурса реферера на котором разместил не реферальные ссылки.
     * @param int $uid Идентификатор пользователя.
     * @return array Возвращает массив в котором должен содержаться идентификатор добавленного URL.
     */
    public function postURLReferal($url, $uid, $update = false, $id = 0)
    {
        $params = array();
        if ($update) {
            $sql = "UPDATE crm_refurls SET url = :url, confirm = FALSE, moderate = FALSE WHERE id = :id AND refid = (SELECT id FROM crm_referals WHERE uid = :uid) RETURNING id";
            $params[] = array(':id', $id, \PDO::PARAM_INT);
        } else
            $sql = "INSERT INTO crm_refurls (refid, url) VALUES ((SELECT id FROM crm_referals WHERE uid = :uid), :url) RETURNING id";
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $params[] = array(':url', $url, \PDO::PARAM_STR);
        $refurl = $this->getSingleRow($sql, $params);
        return $refurl;
    }

    /**
     * Функция выполняет подтверждение добавленного адреса и оповещает модератора о необходимости проверки данного
     * адреса на соответствие текущему рефереру.
     *
     * @param int $uid Идентификатор пользователя.
     * @param string $url URL адрес реферала.
     * @return bool Возвращает результат постановки почтового сообщения в очередь на отправку.
     */
    public function postURLReferalConfirm($uid, $url)
    {
        $sql = "UPDATE crm_refurls SET confirm = TRUE WHERE url = :url AND refid = (SELECT id FROM crm_referals WHERE uid = :uid)";
        $params = array();
        $params[] = array(':url', $url, \PDO::PARAM_STR);
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $this->postSqlQuery($sql, $params);
        $msg = "Наидобрейший денёк, многоуважаемый!\r\n\r\nРеферер подтвердил добавленный ранее URL, необходимо сделать проверку добавленного URL.\r\n\r\nПодтвержденный URL: {$url}\r\nИдентификатор пользователя: {$uid}\r\n\r\nС уважением,\r\nпочтовый мегабот Lead4CRM.";
        $subject = "Lead4CRM: Реферер подтвердил URL";
        $headers = "From: Lead4CRM <noreply@lead4crm.ru>\r\n";
        $headers.= "Reply-To: support@lead4crm.ru\r\n";
        $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
        return mail('arsen@lead4crm.ru', $subject, $msg, $headers);
    }

    /**
     * Функция удаляет пользовательский URL из таблица реферных URL.
     *
     * @param int $id Идентификатор пользовательского URL.
     * @param int $uid Идентификатор пользователя.
     */
    public function deleteURLReferal($id, $uid)
    {
        $sql = "DELETE FROM crm_refurls WHERE id = :id AND refid = (SELECT id FROM crm_referals WHERE uid = :uid)";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $params[] = array(':id', $id, \PDO::PARAM_INT);
        $this->postSqlQuery($sql, $params);
    }

    /**
     * Функция получает данные реферальной программы по конкретному пользователю.
     *
     * @param string $uid Идентификатор пользователя.
     * @return array Возвращает данные по реферальной программе о пользователе.
     */
    public function getUserReferal($uid)
    {
        $sql = "SELECT id, firm, inn, bik, rs, kpp, ks, bank, ur_addr, po_addr, ogrn, okpo, accept, contract FROM crm_referals WHERE uid = :uid";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        return $this->getSingleRow($sql, $params);
    }

    /**
     * Функция формирует список рефералов, принадлежащие конкретному пользователю.
     *
     * @param int $uid Идентификатор пользователя
     * @param int $page Номер страницы
     * @return array Список пользователей рефералов
     */
    public function getAllReferals($uid, $page)
    {
        $limit = 50 * $page;
        $offset = $limit - 50;
        $sql = "SELECT t1.email, count(t1.vk)::INT::BOOLEAN AS vk, count(t1.ok)::INT::BOOLEAN AS ok, count(t1.fb)::INT::BOOLEAN AS fb, count(t1.gp)::INT::BOOLEAN AS gp, count(t1.mr)::INT::BOOLEAN AS mr, count(t1.ya)::INT::BOOLEAN AS ya, t1.company, (SELECT sum(t2.debet) FROM log AS t2 WHERE t1.id = t2.uid) as total, count(t1.id) OVER() AS total_users FROM users AS t1 WHERE t1.refid = (SELECT id FROM crm_referals WHERE uid = :uid) GROUP BY t1.email, t1.company, t1.id ORDER BY total DESC NULLS LAST, t1.id DESC LIMIT 50 OFFSET :offset";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $params[] = array(':offset', $offset, \PDO::PARAM_INT);
        return $this->getMultipleRows($sql, $params);
    }

    /**
     * Функция подсчета сделаных выплат и заработанных средств за счет реферальной программы.
     *
     * @param int $uid Идентификатор пользователя.
     * @return array Возвращает сумму выплат и накомплений для конкретного пользователя.
     */
    public function getFinanceReferals($uid)
    {
        $sql = "SELECT paydate, totalsum as credit FROM crm_reffin WHERE refid = (SELECT id FROM crm_referals WHERE uid = :uid)  ORDER BY paydate ASC";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $credit = $this->getMultipleRows($sql, $params);
        $sql = "SELECT sum(debet) * 0.1 AS debet FROM log WHERE uid IN (SELECT id FROM users WHERE refid = (SELECT id FROM crm_referals WHERE uid = :uid))";
        $debet = $this->getSingleRow($sql, $params);
        return array('credit' => $credit, 'debet' => $debet);
    }

    /**
     * Функция выполянет операции по перечислению денежных средств либо на лицевой счет пользователя, либо на
     * расчетный счет.
     *
     * @param int $uid Идентификатор пользователя.
     * @param string $to Указатель вывода средств.
     * @param int $sum Сумма вывода средств.
     * @return array Выдает результат операции.
     */
    public function getWithdrawals($uid, $to, $sum)
    {
        $finance = $this->getFinanceReferals($uid);
        if ($finance['debet']['debet'] >= $sum) {
            if ($to == 'balance') {
                $params = array();
                $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                $params[] = array(':sum', $sum, \PDO::PARAM_INT);
                $sql = "INSERT INTO crm_reffin (totalsum, refid) VALUES (:sum, (SELECT id FROM crm_referals WHERE uid = :uid)) RETURNING paydate";
                $paydate = $this->getSingleRow($sql, $params);
                $params[] = array(':num', date('ymdHis').rand(0,9), \PDO::PARAM_INT);
                $sql = "WITH invoice_num AS (INSERT INTO invoices (invoice, uid, sum, system) VALUES (:num, :uid, :sum, 'referals') RETURNING id) INSERT INTO log (uid, debet, client, invoice) VALUES (:uid, :sum, 'Реферальная программа: Счет №', (SELECT id FROM invoice_num)) RETURNING modtime";
                $modtime = $this->getSingleRow($sql, $params);
                return array('paydate' => $paydate['paydate'], 'modtime' => $modtime['modtime']);
            } elseif ($to == 'bank') {
                $params = array();
                $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                $params[] = array(':sum', $sum, \PDO::PARAM_INT);
                $sql = "INSERT INTO crm_reffin (totalsum, refid) VALUES (:sum, (SELECT id FROM crm_referals WHERE uid = :uid)) RETURNING paydate, id";
                $paydate = $this->getSingleRow($sql, $params);
                $msg = "Бобрый денек!\r\n\r\nОдин из рефереров заказал выплату на расчетный счет.\r\n\r\nИдентификатор пользователя: {$uid}\r\nИдентификатор выплаты: {$paydate['id']}\r\nСумма заказанной выплаты: {$sum}\r\n\r\nНеобходимо подговторить для него акты сверки и после подписания перечислить средства.\r\n\r\nС поклонением,\r\nпочтовый мегабот сервиса Lead4CRM.";
                $subject = "Lead4CRM: Заявка на выплату";
                $headers = "From: Lead4CRM <noreply@lead4crm.ru>\r\n";
                $headers.= "Reply-To: support@lead4crm.ru\r\n";
                $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
                $mailsend = mail('arsen@lead4crm.ru', $subject, $msg, $headers);
                return array('paydate' => $paydate['paydate'], 'mailsend' => $mailsend);
            } else {
                return array('error' => 'Не верно указана форма выплаты.');
            }
        } else {
            return array('error' => 'Указанная сумма превышает общую сумму накоплений.');
        }
    }

    /**
     * Функция получает все пользовательские URL для конкретного реферера.
     *
     * @param int $uid Идентификатор пользователя.
     * @return array Возвращает массив с полями 'url', 'confirm', 'moderate'.
     */
    public function getURLReferal($uid)
    {
        $sql = "SELECT t1.id, t1.url, t1.confirm, t1.moderate FROM crm_refurls AS t1 LEFT JOIN crm_referals AS t2 ON t1.refid = t2.id WHERE t2.uid = :uid ORDER BY t1.id ASC";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        return $this->getMultipleRows($sql, $params);
    }

    /**
     * Функция производит поиск по реферерным URL адресам и отдает идентификатор реферера для данных URL.
     *
     * @param string $url Реферальный URL адрес.
     * @return array Отдает результат поиска идентификатора реферера со всеми доп. опциями.
     */
    public function getRefererByURL($url)
    {
        $sql = "SELECT t2.uid, t1.confirm, t1.moderate FROM crm_refurls AS t1 LEFT JOIN crm_referals AS t2 ON t1.refid = t2.id WHERE t1.url LIKE :url";
        $params = array();
        $params[] = array(':url', $url, \PDO::PARAM_STR);
        return $this->getSingleRow($sql, $params);
    }

    /**
     * Функция просто выполняет прокладку между backend пользователя и сервисом предоставления информации по БИК.
     *
     * @param string $bik БИК
     * @return string JSON ответ с результатами запроса.
     */
    public function getBIKInfo($bik)
    {
        $bik_search_url = "http://www.bik-info.ru/api.html?type=json&bik=" . $bik;
        return file_get_contents($bik_search_url);
    }

    /**
     * Функция получает данные из справоника 2ГИС через API CNAM РФ по пользовательскому тексту.
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @param string $text Пользовательский текст поиска.
     * @param int $city Идентификатор города в справочнике Lead4CRM.
     * @param string $domain Домен пользователя.
     * @param int $page Номер страницы.
     * @return string Данные в формате JSON.
     */
    public function getDataSearchText($apikey, $text, $city, $domain, $page = 1)
    {
        $url = "http://api.cnamrf.ru/getCompanyList/{$page}/?";
        $uri = http_build_query(
            array(
                'apikey'    => $apikey,
                'text'      => $text,
                'city'      => $city,
                'domain'    => $domain
            )
        );
        return file_get_contents($url.$uri);
    }

    /**
     * Функция получает данные из справоника 2ГИС через API CNAM РФ по названию рубрики.
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @param string $rubric Название рубрики 2ГИС.
     * @param int $city Идентификатор города в справочнике Lead4CRM.
     * @param string $domain Домен пользователя.
     * @param int $page Номер страницы.
     * @return string Данные в формате JSON.
     */
    public function getDataSearchRubric($apikey, $rubric, $city, $domain, $page = 1) {
        $url = "http://api.cnamrf.ru/getCompanyListByRubric/{$page}/?";
        $uri = http_build_query(
            array(
                'apikey'    => $apikey,
                'rubric'    => $rubric,
                'city'      => $city,
                'domain'    => $domain,
            )
        );
        return file_get_contents($url.$uri);
    }

    /**
     * Функция получает список рубрик полный или частичный.
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @param string $domain Домен пользователя.
     * @param bool|false $full Опция указания на получение полного списка, если TRUE, или частичного, если FALSE.
     * @return string Данные в формате JSON.
     */
    public function getRubricList($apikey, $domain, $full = false)
    {
        $url = "http://api.cnamrf.ru/getRubricList/?";
        $uri = http_build_query(
            array(
                'apikey'    => $apikey,
                'domain'    => $domain,
                'full'      => $full,
            )
        );
        return file_get_contents($url.$uri);
    }

    /**
     * Функция отправляет запрос на импорт компании в справочник Lead4CRM через API CNAM РФ и получает результат
     * работы API CNAM РФ в виде JSON-строки.
     *
     * Также функция уведомляет пользователя о выполненном импорте компании у подключеному каналу, если такой имеется.
     * Поддерживаются следующие каналы:
     * - Telegram
     * - Whatsapp (скоро)
     * - ICQ
     * - SMS (очень скоро)
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @param string $domain Домен пользователя. Домен пользователя.
     * @param int $id Идентификатор компании в справочнике 2ГИС.
     * @param string $hash Хэш компании в справочнике 2ГИС.
     * @param int $auid Идентификатор пользователя в Битрикс24.
     * @param string $ip IP-адрес пользователя выполняющего запрос.
     * @param bool $getFrom2GIS Указатель о необходимости принудительного получения данных из справочника 2ГИС.
     * @return string Данные в формате JSON.
     */
    public function getCompanyProfile($apikey, $domain, $id, $hash, $auid, $ip, $getFrom2GIS)
    {
        $url = "http://api.cnamrf.ru/getCompanyProfile/?";
        $uri = http_build_query(
            array(
                'apikey'    => $apikey,
                'domain'    => $domain,
                'id'        => $id,
                'hash'      => $hash,
                'auid'      => $auid,
                'uip'       => $ip,
                '2gis'      => $getFrom2GIS
            )
        );
        $return = file_get_contents($url.$uri);
        $cp = json_decode($return);
        if ($cp->error == '0') {
            $sql = "SELECT id, telegram_chat_id, telegram_company, telegram_balans, icq_uin, icq_company, icq_balans, wa_phone, wa_company, wa_balans, sms_phone, sms_company, sms_balans FROM users WHERE apikey = :apikey";
            $params = array();
            $params[] = array(':apikey', $apikey, \PDO::PARAM_STR);
            $user = $this->getSingleRow($sql, $params);

            // Отправляем уведомления в Telegram.
            if ($user['telegram_balans'] || $user['telegram_company']) {
                $tg = new Telegram($this->conf->telegram->api, $this->conf->telegram->name);
                if ($user['telegram_company'] && !$user['telegram_balans']) {
                    $tg->sendNotification("Импортирована компания: {$cp->name}", $user['telegram_chat_id']);
                } elseif ($user['telegram_balans'] && !$user['telegram_company'] && $cp->summ) {
                    $tg->sendNotification("За импорт компании списана сумма: {$cp->summ} руб.", $user['telegram_chat_id']);
                } else {
                    if ($cp->summ)
                        $tg->sendNotification("За импорт компании {$cp->name} списана сумма: {$cp->summ} руб.", $user['telegram_chat_id']);
                    else
                        $tg->sendNotification("Импортирована компания: {$cp->name}", $user['telegram_chat_id']);
                }
            }

            // Отправляем уведомление в ICQ.
            if ($user['icq_balans'] || $user['icq_company']) {
                if ($user['icq_company'] && !$user['icq_balans']) {
                    $this->sendICQ('sendMsg', $user['icq_uin'], "Импортирована компания:\r\t{$cp->name}");
                } elseif ($user['icq_balans'] && !$user['icq_company'] && $cp->summ) {
                    $this->sendICQ('sendMsg', $user['icq_uin'], "За импорт компании списана сумма:\r\t{$cp->summ} руб.");
                } else {
                    if ($cp->summ)
                        $this->sendICQ('sendMsg', $user['icq_uin'], "За импорт компании:\r\t{$cp->name}\rСписана сумма:\r\t{$cp->summ} руб.");
                    else
                        $this->sendICQ('sendMsg', $user['icq_uin'], "Импортирована компания:\r\t{$cp->name}");
                }
            }

            // Отправляем уведомление в Whatsapp.
            if ($user['wa_balans'] || $user['wa_company']) {}

            // Отправляем уведомление в SMS.
            if ($user['sms_balans'] || $user['sms_company']) {
                if ($user['sms_company'] && !$user['sms_balans']) {
                    $this->sendSMS('sendMsg', $user['sms_phone'], $user['id'], "Импортирована компания: {$cp->name}");
                } elseif ($user['sms_balans'] && !$user['sms_company']) {
                    $this->sendSMS('sendMsg', $user['sms_phone'], $user['id'], "За импорт компании списана сумма: {$cp->summ} руб.");
                } else {
                    if ($cp->summ)
                        $this->sendSMS('sendMsg', $user['sms_phone'], $user['id'], "За импорт компании {$cp->name} списана сумма: {$cp->summ} руб.");
                    else
                        $this->sendSMS('sendMsg', $user['sms_phone'], $user['id'], "Импортирована компания: {$cp->name}");
                }
            }
        }
        return $return;
    }

    /**
     * Функция получает пользовательский кэш — все данные, которые ранее были получены пользователем.
     *
     * @param int $uid Идентификатор пользователя.
     * @return array
     */
    public function getUserCache($uid)
    {
        $sql = "SELECT t1.cp_id AS id, t1.cp_hash AS hash, t2.modtime AS addtime FROM cnam_cache t1 LEFT JOIN log t2 ON t1.logid = t2.id WHERE t2.uid = :uid ORDER BY t2.modtime DESC";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        return $this->getMultipleRows($sql, $params);
    }

    /**
     * Функция собирает данные в единую выборку за опереденный месяц и год и сохраняет в файл.
     *
     * @param string $date Дата выборки в формате ГГГГ-ММ.
     * @param int $crmid Идентификатор CRM системы в справочнике Lead4CRM.
     * @param int $uid Идентификатор пользователя.
     * @throws \PHPExcel_Exception
     */
    public function getSelection($date, $crmid, $uid)
    {
        $sql = "SELECT t1.type, t1.name, t1.template FROM crm_templates t1 LEFT JOIN crm_versions t2 ON t1.id = t2.templateid WHERE t2.id = :crmid";
        $params = array();
        $params[] = array(':crmid', $crmid, \PDO::PARAM_INT);
        $arrTemplate = $this->getSingleRow($sql, $params);
        $type = $arrTemplate['type'];
        $affix = $arrTemplate['name'];
        $template = json_decode($arrTemplate['template'], true);
        $filename = __DIR__.'/ucf/'.$uid.'/2GIS_Base_'.$affix.'_'.$date.'.'.$type;
        if ($type == 'csv') {
            $csv_title = array();
            foreach ($template as $key => $value) {
                $csv_title[] = iconv('UTF-8', 'Windows-1251', $template[$key]['title']);
            }
            $csv = array($csv_title);
            $start_date = $date . '-01';
            $em = str_split($date);
            if ($em[5] == 1 && $em[6] == 2) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_year += 1;
                $end_date = $_year . '-01-01';
            } elseif ($em[5] == 0 && $em[6] == 9) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $end_date = $_year . '-10-01';
            } elseif ($em[5] == 1 && ($em[6] == 0 || $em[6] == 1)) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_month = $em[6] + 1;
                $end_date = $_year . '-1' . $_month . '-01';
            } else {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_month = $em[6] + 1;
                $end_date = $_year . '-0' . $_month . '-01';
            }
            $sql = "SELECT t1.cp_id, t1.cp_hash, t1.lon, t1.lat, t2.modtime AS addtime FROM cnam_cache t1 LEFT JOIN log t2 ON t1.logid = t2.id WHERE t2.uid = :uid AND t2.modtime >= DATE '{$start_date}' AND t2.modtime < DATE '{$end_date}' ORDER BY t2.modtime DESC";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $arrCache = $this->getMultipleRows($sql, $params);
            foreach ($arrCache as $cache) {
                $sql = "SELECT json FROM cnam_cp WHERE id = :cpid AND hash = :cphash";
                $params = array();
                $params[] = array(':cpid', $cache['cp_id'], \PDO::PARAM_INT);
                $params[] = array(':cphash', $cache['cp_hash'], \PDO::PARAM_STR);
                $arrCP = $this->getSingleRow($sql, $params);
                $cp = json_decode($arrCP['json'], true);
                $sql = "SELECT json FROM geodata WHERE lat = :lat AND lon = :lon";
                $params = array();
                $params[] = array(':lat', $cache['lat'], \PDO::PARAM_STR);
                $params[] = array(':lon', $cache['lon'], \PDO::PARAM_STR);
                $arrGD = $this->getSingleRow($sql, $params);
                $gd = json_decode($arrGD['json'], true);
                $csv_line = array();
                foreach ($template as $key => $value) {
                    if ($template[$key]['cp']) {
                        if (preg_match('/^%(.*)%$/', $template[$key]['cp'], $cp_match)) {
                            $_vals = explode('$', $cp_match[1]);
                            if (count($_vals) > 1) {
                                $tmp_arr = array();
                                foreach($_vals as $key) {
                                    $tmp_arr = empty($tmp_arr) ? $cp[$key] : $tmp_arr[$key];
                                }
                                $csv_line[] = iconv('UTF-8', 'Windows-1251', $tmp_arr);
                            } else {
                                $csv_line[] = iconv('UTF-8', 'Windows-1251', $cp[$cp_match[1]]);
                            }
                        } else {
                            if ($template[$key]['argv']) {
                                if (preg_match('/^%(.*)%$/', $template[$key]['argv'], $argv_match)) {
                                    if ($template[$key]['cp'] == 'getMainIndustry')
                                        $_str = $this->getMainIndustry($cp[$argv_match[1]]);
                                    else
                                        $_str = '';
                                    $csv_line[] = iconv('UTF-8', 'Windows-1251', $_str);
                                } else {
                                    if ($template[$key]['cp'] == 'get2GISContact')
                                        $_str = $this->get2GISContact($template[$key]['argv'], $cp, null, $template[$key]['prefix'], $template[$key]['suffix'], $template[$key]['comment']);
                                    else
                                        $_str = '';
                                    $csv_line[] = iconv('UTF-8', 'Windows-1251', $_str);
                                }
                            } else {
                                if ($template[$key]['cp'] == 'bx24Comment')
                                    $_str = $this->bx24Comment($cp);
                                elseif ($template[$key]['cp'] == 'getFullAddress')
                                    $_str = $this->getFullAddress($cp);
                                else
                                    $_str = '';
                                $csv_line[] = iconv('UTF-8', 'Windows-1251', $_str);
                            }
                        }
                    } else if ($template[$key]['gd']) {
                        if (preg_match('/^%(.*)%$/', $template[$key]['gd'], $gd_match)) {
                            $csv_line[] = iconv('UTF-8', 'Windows-1251', $gd['result'][0]['attributes'][$gd_match[1]]);
                        }
                    } else {
                        $csv_line[] = iconv('UTF-8', 'Windows-1251', $template[$key]['default']);
                    }
                }
                $csv[] = $csv_line;
            }
            if (!file_exists(dirname($filename)))
                mkdir(dirname($filename), 0777, true);
            $fp = fopen($filename, 'w');
            foreach ($csv as $line) {
                fputcsv($fp, $line, ";", '"');
            }
            fclose($fp);
        } elseif ($type == 'xls') {
            $xls = new \PHPExcel();
            $xls->getProperties()->setCreator("www.lead4crm.ru");
            $xls->getProperties()->setLastModifiedBy('www.lead4crm.ru');
            $xls->getProperties()->setTitle('2GIS Base at ' . $date);
            $xls->getProperties()->setSubject('2GIS Base');
            $xls->setActiveSheetIndex(0);

            $col = 0;
            $rows = 1;
            $cellType = \PHPExcel_Cell_DataType::TYPE_STRING;
            foreach ($template as $key => $value) {
                $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($template[$key]['title'], $cellType);
                $col++;
            }
            $rows++;
            $start_date = $date . '-01';
            $em = str_split($date);
            if ($em[5] == 1 && $em[6] == 2) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_year += 1;
                $end_date = $_year . '-01-01';
            } elseif ($em[5] == 0 && $em[6] == 9) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $end_date = $_year . '-10-01';
            } elseif ($em[5] == 1 && ($em[6] == 0 || $em[6] == 1)) {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_month = $em[6] + 1;
                $end_date = $_year . '-1' . $_month . '-01';
            } else {
                $_year = $em[0] . $em[1] . $em[2] . $em[3];
                $_month = $em[6] + 1;
                $end_date = $_year . '-0' . $_month . '-01';
            }
            $sql = "SELECT t1.cp_id, t1.cp_hash, t1.lon, t1.lat, t2.modtime AS addtime FROM cnam_cache t1 LEFT JOIN log t2 ON t1.logid = t2.id WHERE t2.uid = :uid AND t2.modtime >= DATE :sdate AND t2.modtime < DATE :edate ORDER BY t2.modtime DESC";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $params[] = array(':sdate', $start_date, \PDO::PARAM_STR);
            $params[] = array(':edate', $end_date, \PDO::PARAM_STR);
            $arrCache = $this->getMultipleRows($sql, $params);
            foreach ($arrCache as $cache) {
                $sql = "SELECT json FROM cnam_cp WHERE id = :cpid AND hash = :cphash";
                $params = array();
                $params[] = array(':cpid', $cache['cp_id'], \PDO::PARAM_INT);
                $params[] = array(':cphash', $cache['cp_hash'], \PDO::PARAM_STR);
                $arrCP = $this->getSingleRow($sql, $params);
                $cp = json_decode($arrCP['json'], true);
                $sql = "SELECT json FROM geodata WHERE lat = :lat AND lon = :lon";
                $params = array();
                $params[] = array(':lat', $cache['lat'], \PDO::PARAM_STR);
                $params[] = array(':lon', $cache['lon'], \PDO::PARAM_STR);
                $arrGD = $this->getSingleRow($sql, $params);
                $gd = json_decode($arrGD['json'], true);
                $col = 0;
                foreach ($template as $key => $value) {
                    if ($template[$key]['cp']) {
                        if (preg_match('/^%(.*)%$/', $template[$key]['cp'], $cp_match)) {
                            $_vals = explode('$', $cp_match[1]);
                            if (count($_vals) > 1) {
                                $tmp_arr = array();
                                foreach($_vals as $key) {
                                    $tmp_arr = empty($tmp_arr) ? $cp[$key] : $tmp_arr[$key];
                                }
                                $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($tmp_arr, $cellType);
                            } else {
                                $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($cp[$cp_match[1]], $cellType);
                            }
                        } else {
                            if ($template[$key]['argv']) {
                                if (preg_match('/^%(.*)%$/', $template[$key]['argv'], $argv_match)) {
                                    if ($template[$key]['cp'] == 'getMainIndustry')
                                        $_str = $this->getMainIndustry($cp[$argv_match[1]]);
                                    else
                                        $_str = '';
                                    $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($_str, $cellType);
                                } else {
                                    if ($template[$key]['cp'] == 'get2GISContact')
                                        $_str = $this->get2GISContact($template[$key]['argv'], $cp, null, $template[$key]['prefix'], $template[$key]['suffix'], $template[$key]['comment']);
                                    else
                                        $_str = '';
                                    $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($_str, $cellType);
                                }
                            } else {
                                if ($template[$key]['cp'] == 'bx24Comment')
                                    $_str = $this->bx24Comment($cp);
                                elseif ($template[$key]['cp'] == 'getFullAddress')
                                    $_str = $this->getFullAddress($cp);
                                else
                                    $_str = '';
                                $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($_str, $cellType);
                            }
                        }
                    } else if ($template[$key]['gd']) {
                        if (preg_match('/^%(.*)%$/', $template[$key]['gd'], $gd_match)) {
                            $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($gd['result'][0]['attributes'][$gd_match[1]], $cellType);
                        }
                    } else {
                        $xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($template[$key]['default'], $cellType);
                    }
                    $col++;
                }
                $rows++;
            }
            foreach (range('A', $xls->getActiveSheet()->getHighestDataColumn()) as $col) {
                $xls->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
                $xls->getActiveSheet()->getStyle($col.'1')->getFont()->setBold(true);
            }
            $xls->getActiveSheet()->setTitle('Выборка из 2ГИС');

            if (!file_exists(dirname($filename)))
                mkdir(dirname($filename), 0777, true);
            $xlsw = new \PHPExcel_Writer_Excel5($xls);
            $xlsw->save($filename);
        }
        self::getFile($date, $type, $affix);
    }

    /**
     * Функция получения выборки за опеределенную дату в виде массива или кодированной JSON строки.
     *
     * @param string $date Дата выборки в формате ГГГГ-ММ.
     * @param int $crmid Идентификатор CRM системы в справочнике Lead4CRM.
     * @param int $uid Идентификатор пользователя.
     * @param bool|false $json
     * @param bool|false $useAddon
     * @return array
     */
    public function getSelectionArray($date, $crmid, $uid, $json = false, $useAddon = false)
    {
        $sql = "SELECT t1.template FROM crm_templates t1 LEFT JOIN crm_versions t2 ON t1.id = t2.templateid WHERE t2.id = :crmid";
        $params = array();
        $params[] = array(':crmid', $crmid, \PDO::PARAM_INT);
        $arrTemplate = $this->getSingleRow($sql, $params);
        $template = json_decode($arrTemplate['template'], true);
        $_return = array();
        $start_date = $date . '-01';
        $em = str_split($date);
        if ($em[5] == 1 && $em[6] == 2) {
            $_year = $em[0] . $em[1] . $em[2] . $em[3];
            $_year += 1;
            $end_date = $_year . '-01-01';
        } elseif ($em[5] == 0 && $em[6] == 9) {
            $_year = $em[0] . $em[1] . $em[2] . $em[3];
            $end_date = $_year . '-10-01';
        } elseif ($em[5] == 1 && ($em[6] == 0 || $em[6] == 1)) {
            $_year = $em[0] . $em[1] . $em[2] . $em[3];
            $_month = $em[6] + 1;
            $end_date = $_year . '-1' . $_month . '-01';
        } else {
            $_year = $em[0] . $em[1] . $em[2] . $em[3];
            $_month = $em[6] + 1;
            $end_date = $_year . '-0' . $_month . '-01';
        }
        $sql = "SELECT t1.cp_id, t1.cp_hash, t1.lon, t1.lat, t2.modtime AS addtime FROM cnam_cache t1 LEFT JOIN log t2 ON t1.logid = t2.id WHERE t2.uid = :uid AND t2.modtime >= DATE '{$start_date}' AND t2.modtime < DATE '{$end_date}' ORDER BY t2.modtime DESC";
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $arrCache = $this->getMultipleRows($sql, $params);
        foreach ($arrCache as $cache) {
            $sql = "SELECT json FROM cnam_cp WHERE id = :cpid AND hash = :cphash";
            $params = array();
            $params[] = array(':cpid', $cache['cp_id'], \PDO::PARAM_INT);
            $params[] = array(':cphash', $cache['cp_hash'], \PDO::PARAM_STR);
            $arrCP = $this->getSingleRow($sql, $params);
            $cp = json_decode($arrCP['json'], true);
            $sql = "SELECT json FROM geodata WHERE lat = :lat AND lon = :lon";
            $params = array();
            $params[] = array(':lat', $cache['lat'], \PDO::PARAM_STR);
            $params[] = array(':lon', $cache['lon'], \PDO::PARAM_STR);
            $arrGD = $this->getSingleRow($sql, $params);
            $gd = json_decode($arrGD['json'], true);
            $_compnay = array();
            foreach ($template as $key => $value) {
                if ($template[$key]['cp']) {
                    if (preg_match('/^%(.*)%$/', $template[$key]['cp'], $cp_match)) {
                        $_vals = explode('$', $cp_match[1]);
                        if (count($_vals) > 1) {
                            $tmp_arr = array();
                            foreach($_vals as $key2) {
                                $tmp_arr = empty($tmp_arr) ? $cp[$key2] : $tmp_arr[$key2];
                            }
                            $_compnay[$key] = $tmp_arr;
                        } else {
                            $_compnay[$key] = $cp[$cp_match[1]];
                        }
                    } else {
                        if ($template[$key]['argv']) {
                            if (preg_match('/^%(.*)%$/', $template[$key]['argv'], $argv_match)) {
                                if ($template[$key]['cp'] == 'getMainIndustry')
                                    $_str = $this->getMainIndustry($cp[$argv_match[1]]);
                                else
                                    $_str = '';
                                $_compnay[$key] = $_str;
                            } else {
                                if ($template[$key]['cp'] == 'get2GISContact')
                                    $_str = $this->get2GISContact($template[$key]['argv'], $cp, null, $template[$key]['prefix'], $template[$key]['suffix'], $template[$key]['comment'], $useAddon);
                                else
                                    $_str = '';
                                $_compnay[$key] = $_str;
                            }
                        } else {
                            if ($template[$key]['cp'] == 'bx24Comment')
                                $_str = $this->bx24Comment($cp);
                            elseif ($template[$key]['cp'] == 'getFullAddress')
                                $_str = $this->getFullAddress($cp);
                            else
                                $_str = '';
                            $_compnay[$key] = $_str;
                        }
                    }
                } else if ($template[$key]['gd']) {
                    if (preg_match('/^%(.*)%$/', $template[$key]['gd'], $gd_match)) {
                        $_compnay[$key] = $gd['result'][0]['attributes'][$gd_match[1]];
                    }
                } else {
                    $_compnay[$key] = $template[$key]['default'];
                }
            }
            $_return[] = $_compnay;
        }
        $return = array('opt' => $_return, 'total' => count($_return));
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode($return, JSON_UNESCAPED_UNICODE);
        }
        return $return;
    }

    /**
     * Функция генерирует и отправляет счет на оплату для конкретной компании.
     *
     * @param float $userSumm Сумма счета.
     * @param string $userCompany Наименование юридического лица.
     * @param int $uid Идентификатор пользователя.
     */
    public function getInvoice($userSumm, $userCompany, $uid)
    {
        global $twig;
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, true);

        $pdf->SetCreator('Lead4CRM');
        $pdf->SetAuthor('Arsen Bespalov');
        $pdf->SetTitle('Lead4CRM Invoice');
        $pdf->SetSubject('Invoice');
        $pdf->SetKeywords('lead4crm, invoice');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(18, 10, 32, true);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $pdf->SetFont('arial', '', 9);
        $pdf->AddPage();

        $user_sum = str_replace(',', '.', $userSumm);
        $user_sum = str_replace(' ', '', $user_sum);
        $sum = number_format($user_sum, 2, '-', ' ');
        $sum_alt = number_format($user_sum, 2, '.', '\'');
        $invoice_num = date('ymdHis').rand(0,9);
        $html = $twig->render('invoice.twig',
            array(
                'invoice_number'    => 'L4CRM-'.$this->postInvoice($invoice_num, $user_sum, $uid),
                'invoice_date'      => $this->getDateRUS().' г.',
                'client_company'    => $this->postUserCompany($userCompany, $uid),
                'userid'            => $uid,
                'price'             => $sum,
                'summ'              => $sum,
                'summ_alt'          => $sum_alt,
                'total'             => $sum,
                'summ_text'         => self::mb_ucfirst(self::summ2str($user_sum)),
            )
        );
        $pdf->writeHTML($html, true, 0, true, 0);
        $pdf->Image(K_PATH_IMAGES . 'print_trans.png', 21, 140, 40, '', '', '', '', false);
        $pdf->Image(K_PATH_IMAGES . 'sign_trans.png', 50, 124, 60, '', '', '', '', false);

        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255,0,0)));
        $pdf->SetFillColor(255,255,128);
        $pdf->SetTextColor(0,0,128);
        $text = 'ВНИМАНИЕ! После оплаты отправьте платежное поручение по адресу: support@lead4crm.ru';
        $pdf->Ln(30);
        $pdf->Cell(0, 10, $text, 1, 1, 'L', 1, 0);

        $pdf->lastPage();
        $pdf->Output("Invoice_L4CRM-{$invoice_num}.pdf", 'D');
    }

    /**
     * Приватная функция выполняет запись счета в БД.
     *
     * @param string $num
     * @param double $sum
     * @param int $uid
     * @param string $system
     * @return string
     */
    private function postInvoice($num, $sum, $uid, $system = 'bank')
    {
        $sql = "INSERT INTO invoices (invoice, uid, sum, system) VALUES (:num, :uid, :sum, :system)";
        $params = array();
        $params[] = array(':num', $num, \PDO::PARAM_STR);
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $params[] = array(':sum', $sum, \PDO::PARAM_STR);
        $params[] = array(':system', $system, \PDO::PARAM_STR);
        $this->postSqlQuery($sql, $params);
        return $num;
    }

    /**
     * Приватная функция сохраняет название компании польвателя в БД.
     *
     * @param string $company Название компании пользователя.
     * @param int $uid Идентифкатор пользователя.
     * @return string Название компании пользователя.
     */
    private function postUserCompany($company, $uid)
    {
        $sql = "UPDATE users SET company = :company WHERE id = :uid";
        $params = array();
        $params[] = array(':company', $company, \PDO::PARAM_STR);
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $this->postSqlQuery($sql, $params);
        return $company;
    }

    /**
     * Функция выполняет проверку и подтверждение (зачисление средств на баланс пользователя) платежей.
     * Работает с платежным шлюзом Яндекс.Касса.
     *
     * @param string $cmd Команда check (проверка платежа) или aviso (подтверждение платежа).
     * @return string Данные в формате XML.
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function getPayment($cmd)
    {
        $tg = new Telegram($this->conf->telegram->api, $this->conf->telegram->name);
        $performedDatetime = date(DATE_W3C);

        $message = 'Что-то пошло не так!';
        $techMessage = 'Вернитесь назад и попробуйте снова. Возможно на этапе проведения платежа потерялось часть данных.';

        $shopId = $this->conf->payments->ShopID;
        $shopPassword = $this->conf->payments->ShopPassword;

        $yaAction = $_POST['action'];
        $yaOrderSumAmount = $_POST['orderSumAmount'];
        $yaOrderSumCurrencyPaycash = $_POST['orderSumCurrencyPaycash'];
        $yaOrderSumBankPaycash = $_POST['orderSumBankPaycash'];
        $yaShopId = $_POST['shopId'];
        $yaInvoiceId = $_POST['invoiceId'];
        $yaCustomerNumber = $_POST['customerNumber'];
        $yaMD5 = $_POST['md5'];
        $yaPaymentType = $_POST['paymentType'];

        switch ($yaPaymentType) {
            case 'PC':
                $client = 'Яндекс.Деньги: Счет № ';
                break;
            case 'AC':
                $client = 'Банковская карта: Счет № ';
                break;
            case 'MC':
                $client = 'Мобильный телефон: Счет № ';
                break;
            case 'GP':
                $client = 'Наличные: Счет № ';
                break;
            case 'WM':
                $client = 'WebMoney: Счет № ';
                break;
            case 'SB':
                $client = 'Сбербанк: Счет № ';
                break;
            case 'AB':
                $client = 'Альфа-Клик: Счет № ';
                break;
            case 'МА':
                $client = 'MasterPass: Счет № ';
                break;
            case 'PB':
                $client = 'Промсвязьбанк: Счет № ';
                break;
            default:
                $client = 'Хуй знает: Счет № ';
        }

        $response = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

        if ($cmd == 'check') {
            $checkOrderStr = array(
                $yaAction,
                $yaOrderSumAmount,
                $yaOrderSumCurrencyPaycash,
                $yaOrderSumBankPaycash,
                $shopId,
                $yaInvoiceId,
                $yaCustomerNumber,
                $shopPassword,
            );
            $md5 = strtoupper(md5(implode(';', $checkOrderStr)));

            if ($md5 != $yaMD5) {
                $code = '100';
            } else {
                $code = '0';
                $sql = "INSERT INTO invoices (invoice, uid, sum, system) VALUES (:invoice, :uid, :sum, :system)";
                $params = array();
                $params[] = array(':invoice', $yaInvoiceId, \PDO::PARAM_INT);
                $params[] = array(':uid', $yaCustomerNumber, \PDO::PARAM_INT);
                $params[] = array(':sum', $yaOrderSumAmount, \PDO::PARAM_STR);
                $params[] = array(':system', 'yamoney:'.$yaPaymentType, \PDO::PARAM_STR);
                $this->postSqlQuery($sql, $params);
            }

            if ($code) {
                $error_msg = "message=\"{$message}\" techMessage=\"{$techMessage}\"";
            } else {
                $error_msg = '';
            }

            $response .= "<checkOrderResponse performedDatetime=\"{$performedDatetime}\" code=\"{$code}\" invoiceId=\"{$yaInvoiceId}\" shopId=\"{$yaShopId}\" {$error_msg} />";
        } elseif ($cmd == 'aviso') {
            $sql = "SELECT id, uid, invoice, sum FROM invoices WHERE uid = :uid AND invoice = :invoice AND sum = :sum";
            $params = array();
            $params[] = array(':uid', $yaCustomerNumber, \PDO::PARAM_INT);
            $params[] = array(':invoice', $yaInvoiceId, \PDO::PARAM_INT);
            $params[] = array(':sum', $yaOrderSumAmount, \PDO::PARAM_STR);
            $invoice = $this->getSingleRow($sql, $params);
            $iid = $invoice['id'];
            $uid = $invoice['uid'];
            $yiid = $invoice['invoice'];
            $sum = $invoice['sum'];
            if ($iid) {
                $checkOrderStr = array(
                    $yaAction,
                    number_format($sum, 2, '.', ''),
                    $yaOrderSumCurrencyPaycash,
                    $yaOrderSumBankPaycash,
                    $shopId,
                    $yiid,
                    $uid,
                    $shopPassword,
                );
                $md5 = strtoupper(md5(implode(';', $checkOrderStr)));
                if ($md5 != $yaMD5) {
                    $code = '1';
                } else {
                    $code = '0';
                    $sql = "INSERT INTO log (uid, debet, client, invoice) VALUES (:uid, :sum, :client, :invoice)";
                    $params = array();
                    $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                    $params[] = array(':sum', $sum, \PDO::PARAM_STR);
                    $params[] = array(':client', $client, \PDO::PARAM_STR);
                    $params[] = array(':invoice', $iid, \PDO::PARAM_INT);
                    $this->postSqlQuery($sql, $params);
                    $sql = "SELECT telegram_chat_id, telegram_balans, icq_uin, icq_balans, sms_phone, sms_balans, wa_phone, wa_balans FROM users WHERE id = :uid";
                    $params = array();
                    $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                    $user = $this->getSingleRow($sql, $params);
                    if ($user['telegram_balans'] == 't')
                        $tg->sendNotification("Лицевой счет пополнен на сумму: {$sum} руб.", $user['telegram_chat_id']);
                    if ($user['icq_balans'] == 't')
                        $this->sendICQ('sendMsg', $user['icq_uin'], "Лицевой счет пополнен на сумму:\r\t{$sum} руб.");
                    if ($user['sms_balans'] == 't')
                        $this->sendSMS('sendMsg', $user['sms_phone'], $uid, "Лицевой счет пополнен на сумму: {$sum} руб.");
                }
            } else {
                $code = '200';
            }
            $response .= "<paymentAvisoResponse performedDatetime=\"{$performedDatetime}\" code=\"{$code}\" invoiceId=\"{$yaInvoiceId}\" shopId=\"{$yaShopId}\"/>";
        }
        file_put_contents('/var/www/html/ya_debug.log', $response . "\r\n===\r\n", FILE_APPEND | LOCK_EX);
        return $response;
    }

    /**
     * Функция получает список поддерживаемых городов и возвращает их в кодированном JSON формате.
     * Изначательно список городов смотрится в стационарном файле, если файл не найден, то обращение делается к БД и
     * результат сохраняется во временный файл городов.
     *
     * @return string Данные в формате JSON.
     */
    public function getSupportCities()
    {
        if (file_exists(__DIR__.'/cities.json')) {
            $json = file_get_contents(__DIR__.'/cities.json');
        } else {
            $sql = "SELECT id, name FROM cities ORDER BY name ASC";
            $cities = $this->getMultipleRows($sql, array());
            $json = json_encode($cities, JSON_UNESCAPED_UNICODE);
            file_put_contents(__DIR__.'/cities.json', $json);
        }
        return $json;
    }

    /**
     * Функция возвращает идентификатор пользователя в системе или возвращает null в случает отсутствия пользователя.
     *
     * @param string $apikey Пользовательский ключ доступа.
     * @return array Возвращает идентификатор пользователя.
     */
    public function checkAPIKey($apikey)
    {
        $sql = "SELECT id FROM users WHERE apikey = :apikey";
        $params = array();
        $params[] = array(':apikey', $apikey, \PDO::PARAM_STR);
        $user = $this->getSingleRow($sql, $params);
        return array('userid' => $user['id']);
    }

    /**
     * Функция генерирует новый пользовательский ключ доспута.
     *
     * @param int $uid Идентификатор пользователя.
     * @return string Новый пользовательский ключ доступа.
     */
    public function getNewAPIKey($uid)
    {
        $apikey = sha1($_SERVER['HTTP_USER_AGENT'].time());
        $sql = "UPDATE users SET apikey = :apikey WHERE id = :uid";
        $params = array();
        $params[] = array(':apikey', $apikey, \PDO::PARAM_STR);
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $this->postSqlQuery($sql, $params);
        $_SESSION['apikey'] = $apikey;
        return $_SESSION['apikey'];
    }

    /**
     * Функция генерирует электронную адресную карточку.
     *
     * @return string vCard
     */
    static public function getVCard()
    {
        $vcard = "BEGIN:VCARD\n";
        $vcard.= "VERSION:3.0\n";
        $vcard.= "FN:Lead4CRM («Генератор лидов»)\n";
        $vcard.= "ORG:Lead4CRM («Генератор лидов»)\n";
        $vcard.= "NOTE:Базы 2ГИС в формате любимой CRM\n";
        $vcard.= "TEL;TYPE=work:+7 (3952) 96-96-17\n";
        $vcard.= "TEL;TYPE=cell:+7 (914) 926-96-17\n";
        $vcard.= "EMAIL;TYPE=internet:support@lead4crm.ru\n";
        $vcard.= "EMAIL;TYPE=internet,pref:help@lead4crm.ru\n";
        $vcard.= "EMAIL;TYPE=internet:news@lead4crm.ru\n";
        $vcard.= "PHOTO;VALUE=uri:https://www.lead4crm.ru/public/images/vcard-logo-1024x1024.jpg\n";
        $vcard.= "LOGO;VALUE=uri:https://www.lead4crm.ru/public/images/vcard-logo-1024x1024.jpg\n";
        $vcard.= "URL:https://www.lead4crm.ru\n";
        $vcard.= "X-ICQ:658127246\n";
        $vcard.= "END:VCARD\n";
        return $vcard;
    }

    /**
     * Функция пытается определить IP-адрес пользователя и в случаи удачи возвращет его, в случае неудачи
     * возращает дефолтный IP-адрес в системе.
     *
     * @return string Возвращает IP-адрес пользователя по возможности.
     */
    static public function getRealIpAddr() {
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = '77.88.8.8';
        return $ipaddress;
    }

    /**
     * Функция выполняет занимается доставкой сообщений в месенджер ICQ.
     *
     * @param string $cmd Команда для предварительной процедуры перед отправкой сообщения.
     * @param int $uin Пользовательский UIN.
     * @param string $msg Сообщение, которое будет отправлено указанному UIN.
     */
    public function sendICQ($cmd = '', $uin = 0, $msg = '')
    {
        $icq = new \WebIcqPro();
        $icq->debug;
        $icq->setOption('UserAgent', 'macicq');
        if ($icq->connect($this->conf->icq->uin, $this->conf->icq->password)) {
            switch ($cmd) {
                case 'sendCode':
                    $code = '';
                    for ($x = 0; $x < 2; $x++) {
                        for ($y = 0; $y < 3; $y++) {
                            $code .= mt_rand(0,9);
                        }
                        $code .= '-';
                    }
                    $code = substr($code, 0, -1);
                    $_SESSION['icq']['code'] = preg_replace('/[^0-9]/', '', $code);
                    $msg = '-- Ваш код подтверждения: '.$code."\r";
                    $msg.= 'Введите данный код на сайте www.lead4crm.ru.'."\r\n";
                    $msg.= 'Если вы не имеете никакого отношения к данному сайту и не делали никаких действий на данном сайте, то просто проигнорируйте это сообщение.'."\r\n";
                    break;

                case 'save':
                    $sUin = $_SESSION['icq']['uin'];
                    $uUin = preg_replace('/[^0-9]/', '', $_REQUEST['uin']);
                    $sCode = $_SESSION['icq']['code'];
                    $uCode = preg_replace('/[^0-9]/', '', $_REQUEST['code']);
                    if (($sUin != $uUin && $sCode == $uCode) ||	$sUin == $uUin) {
                        $query_notify = '';
                        $noties = array('company' => 'false', 'balans' => 'false', 'renewal' => 'false');
                        $msg = "Теперь на ваш UIN ({$uUin}) будут поступать следующие уведомления: \r";
                        $msg_len = strlen($msg);
                        foreach ($_REQUEST['notify'] as $notify) {
                            switch ($notify) {
                                case 'company':
                                    $msg .= "\t- Уведомления об импорте компаний\r";
                                    $noties['company'] = 'true';
                                    $_SESSION['icq']['company'] = true;
                                    break;

                                case 'balans':
                                    $msg .= "\t- Уведомления об изменении баланса лицевого счета\r";
                                    $noties['balans'] = 'true';
                                    $_SESSION['icq']['balans'] = true;
                                    break;

                                case 'renewal':
                                    $msg .= "\t- Уведомления о предстоящем продлении тарифного плана\r";
                                    $noties['renewal'] = 'true';
                                    $_SESSION['icq']['renewal'] = true;
                                    break;
                            }
                        }

                        if ($msg_len == strlen($msg)) {
                            $msg .= "\t- Ни одного уведомления не получите, т.к. всё отключено\r";
                        }

                        foreach ($noties as $notify_key => $notify_status) {
                            $query_notify .= ', icq_'.$notify_key.' = '.$notify_status;
                        }

                        $sql = "UPDATE users SET icq_uin = :uuin {$query_notify} WHERE id = :uid";
                        $params = array();
                        $params[] = array(':uuin', $uUin, \PDO::PARAM_INT);
                        $params[] = array(':uid', $_SESSION['userid'], \PDO::PARAM_INT);
                        $this->postSqlQuery($sql, $params);
                        $_SESSION['icq']['uin'] = $uUin;
                    } else {
                        echo 'error_code';
                        exit();
                    }
                    break;

                case 'sendMsg':
                default:
            }
            $icq->sendMessage($uin, mb_convert_encoding($msg, 'cp1251', 'UTF-8'));
            $icq->setStatus('STATUS_FREE4CHAT', 'STATUS_DCAUTH', 'Yo!');
            $icq->setXStatus('business', 'Yo!');
            sleep(1);
            $icq->disconnect();
        }
    }

    /**
     * Функция позволяет отправлять смс сообщения по указанному номеру телефона.
     *
     * @param string $cmd Комманда для выполнения, поддерживаются:
     * - sendCode (отправка кода подтверждения)
     * - getStatus (узнать статус о доставке смс сообщения)
     * - sendMsg (отправить смс сообщение)
     * - save (сохранение параметров пользователя)
     * - getInfo (узнать о текущем состоянии смс агрегатора и балансе пользователя)
     * @param string $phone Номер телефона в любом формате, главное содержащий нужные цифры.
     * @param int $uid
     * @param string $msg
     * @return array Массив данных с результатом обработки данных.
     * @throws \Zelenin\SmsRu\Exception\Exception
     */
    public function sendSMS($cmd, $phone, $uid, $msg = '')
    {
        $sms = new \Zelenin\SmsRu\Api(new ApiIdAuth($this->conf->sms->api));
        $udata = $this->getUserDataByUID($uid);
        $_result = array();
        if ($cmd == 'sendCode') {
            $code = '';
            for ($x = 0; $x < 2; $x++) {
                for ($y = 0; $y < 3; $y++) {
                    $code .= mt_rand(0, 9);
                }
                $code .= '-';
            }
            $code = substr($code, 0, -1);
            $_SESSION['sms']['code'] = preg_replace('/[^0-9]/', '', $code);
            $codeTxt = 'Код подтверждения: ' . $code;
            $_result = $this->sendSMS('sendMsg', $phone, $uid, $codeTxt);
        } elseif ($cmd == 'getStatus') {
            $_result['response'] = $sms->smsStatus($phone);
        } elseif ($cmd == 'sendMsg') {
            $smsMsg = new Sms($phone, $msg);
            $smsMsg->from = 'Lead4CRM';
            $smsMsg->partner_id = 132872;
            $smsInfo = $this->sendSMS('getInfo', $phone, $uid, $msg);
            if ($udata['balance'] >= $smsInfo['agregator']['cost']) {
                $_result['response'] = $sms->smsSend($smsMsg);
                $_result['response']['error'] = 0;
                $_result['response']['cost'] = $smsInfo['agregator']['cost'];
                $sql = "INSERT INTO log (uid, credit, client) VALUES (:uid, :cost, 'SMS сообщение')";
                $params = array();
                $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                $params[] = array(':cost', $smsInfo['agregator']['cost']);
                $this->postSqlQuery($sql, $params);
            } else {
                $_result['response']['error'] = '200';
            }
        } elseif ($cmd == 'save') {
            $sPhone = $_SESSION['sms']['phone'];
            $uPhone = preg_replace('/[^0-9]/', '', $phone);
            $sCode = $_SESSION['sms']['code'];
            $uCode = preg_replace('/[^0-9]/', '', $_REQUEST['code']);
            if (($sPhone != $uPhone && $sCode == $uCode) ||	$sPhone == $uPhone) {
                $query_notify = '';
                $noties = array('company' => 'false', 'balans' => 'false', 'renewal' => 'false');
                $smsTxt = "На ваш номер установлены уведомления: ";
                $smsTxt_len = strlen($smsTxt);
                foreach ($_REQUEST['notify'] as $notify) {
                    switch ($notify) {
                        case 'company':
                            $smsTxt .= "- Об импорте компаний. ";
                            $noties['company'] = 'true';
                            $_SESSION['icq']['company'] = true;
                            break;

                        case 'balans':
                            $smsTxt .= "- Об изменении баланса. ";
                            $noties['balans'] = 'true';
                            $_SESSION['icq']['balans'] = true;
                            break;

                        case 'renewal':
                            $smsTxt .= "- О предстоящем продлении тарифного плана. ";
                            $noties['renewal'] = 'true';
                            $_SESSION['icq']['renewal'] = true;
                            break;
                    }
                }
                if ($smsTxt_len == strlen($smsTxt)) {
                    $smsTxt = "На ваш номер не установлены уведомления.";
                }
                $_result = $this->sendSMS('sendMsg', $phone, $uid, $smsTxt);
                if ($_result['response']['error'] == 0) {
                    foreach ($noties as $notify_key => $notify_status) {
                        $query_notify .= ', sms_'.$notify_key.' = '.$notify_status;
                    }
                    $sql = "UPDATE users SET sms_phone = :phone {$query_notify} WHERE id = :uid";
                    $params = array();
                    $params[] = array(':phone', $uPhone, \PDO::PARAM_INT);
                    $params[] = array(':uid', $uid, \PDO::PARAM_INT);
                    $this->postSqlQuery($sql, $params);
                    $_SESSION['sms']['phone'] = $uPhone;
                }
            } else {
                $_result['response']['error'] = '100';
            }
        } elseif ($cmd == 'getInfo') {
            $sms_cost = $sms->smsCost(new Sms($phone, $msg));
            $sms_cost = round($sms_cost->price + ($sms_cost->price * 0.4), 2);
            $_result['agregator'] = array(
                'balance' => $sms->myBalance(),
                'limit' => $sms->myLimit(),
                'sernders' => $sms->mySenders(),
                'cost' => $sms_cost,
            );
            $_result['balance'] = $udata['balance'];
            $_result['command'] = $cmd;
            $_result['to'] = $phone;
        }
        return $_result;
    }

    /**
     * Функция отправляет уведомление по электронной почте об изменении типов подписки, также функция отправляет по
     * электронной почте письма с кодом подтверждения и подтверждает в случае необходимости электронную почту
     * пользователя.
     *
     * @param string $cmd Команда:
     * - code (отправка письма с кодом подтверждения)
     * - confirm (подтверждение электронной почты пользователя)
     * - change (изменение параметров уведомления)
     * @param string $email Электронная почта пользователя
     * @param int $uid Идентификатор пользователя
     * @param string|null $subject Тема сообщения
     * @param string|null $msg Текст сообщения
     * @param string|null $headers Заголовки письма
     * @return bool Возвращает результат принятия сообщения на доставку.
     */
    public function sendEmail($cmd, $email, $uid, $subject = null, $msg = null, $headers = null)
    {
        if ($cmd == 'code') {
            $email = strtolower($email);
            $notify = implode(',', $_REQUEST['notify']);
            $code = exec("/opt/lds/email --encode '{$email};{$notify}'");
            $code = urlencode($code);
            $email_url = urlencode($email);
            $msg = "Здравствуйте!\r\n\r\nКто-то, возможно вы, на сайте www.lead4crm.ru указали данный адрес электронной почты как основной адрес для получения уведомлений.\r\n\r\nЕсли это были не вы, то просто удалите данное письмо.\r\n\r\nНо если вы подтвердите данный адрес, то вы сможете на него получать уведомления о предстоящем продлении тарифного плана на сайте www.lead4crm.ru.\r\n\r\nНастройка уведомлений делается там же. Подтверждение адреса вовсе не является включением уведомлений, для этого помимо указания нового адреса электронной почты необходимо отметить нужные пункты уведомлений.\r\n\r\nДля подтверждения пройдите по следующему адресу:\r\n\r\nhttps://www.lead4crm.ru/email/confirm/?email={$email_url}&code={$code}\r\n\r\nВНИМАНИЕ! Для подтверждения адреса необходима действующая авторизация на сайте www.lead4crm.ru.\r\n\r\n---\r\nС уважением,\r\nколлетив сайта www.lead4crm.ru\r\nE-mail: support@lead4crm.ru\r\nТел.: +7 (3952) 96-96-17\r\nБазы 2ГИС официально и быстро!";
            $subject = "Lead4CRM: Подтверждение электронной почты";
            $headers = "From: noreply@lead4crm.ru\r\n";
            $headers.= "Reply-To: support@lead4crm.ru\r\n";
            $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
        } elseif ($cmd == 'confirm') {
            $query_notify = '';
            $noties = array('renewal' => 'false');
            $code = urldecode($_REQUEST['code']);
            $decode = exec("/opt/lds/email --decode '{$code}'");
            list($email, $dNoties) = explode(';', $decode);
            $arrNoties = explode(',', $dNoties);
            $msg = "Здравствуйте!\r\n\r\nВаш адрес электронной почты подтвержден.\r\n";
            $msg.= "Теперь на ваш адрес будут поступать следующие уведомления:\r\n";
            $msg_len = strlen($msg);
            foreach ($arrNoties as $notify) {
                switch($notify) {
                    case 'renewal':
                        $msg .= "\t- Уведомления о предстоящем продлении тарифного плана\r\n";
                        $noties['renewal'] = 'true';
                        $_SESSION['email']['renewal'] = true;
                        break;
                }
            }
            if ($msg_len == strlen($msg)) {
                $msg .= "\t- Ни одного уведомления не получите, т.к. всё отключено\r\n";
            }
            foreach ($noties as $notify_key => $notify_status) {
                $query_notify .= ', email_'.$notify_key.' = '.$notify_status;
            }
            $sql = "UPDATE users SET email = :email {$query_notify} WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $params[] = array(':email', $email, \PDO::PARAM_STR);
            $this->postSqlQuery($sql, $params);
            $_SESSION['email']['address'] = $email;
            $msg .= "\r\n\r\n---\r\nС уважением,\r\nколлетив сайта www.lead4crm.ru\r\nE-mail: support@lead4crm.ru\r\nТел.: +7 (499) 704-69-17\r\nБазы 2ГИС официально и быстро!";
            $subject = 'Lead4CRM: Адрес подтвержден';
            $headers = "From: noreply@lead4crm.ru\r\n";
            $headers.= "Reply-To: support@lead4crm.ru\r\n";
            $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
        } elseif ($cmd == 'change') {
            $query_notify = '';
            $noties = array('renewal' => 'false');
            $msg = "Здравствуйте!\r\n\r\nДля вашего адреса установлены следующие уведомления:\r\n";
            $msg_len = strlen($msg);
            foreach ($_REQUEST['notify'] as $notify) {
                switch ($notify) {
                    case 'renewal':
                        $msg .= "\t- Уведомления о предстоящем продлении тарифного плана\r\n";
                        $noties['renewal'] = 'true';
                        $_SESSION['icq']['renewal'] = true;
                        break;
                }
            }
            if ($msg_len == strlen($msg)) {
                $msg .= "\t- Ни одного уведомления не получите, т.к. всё отключено\r\n";
            }
            foreach ($noties as $notify_key => $notify_status) {
                $query_notify .= ', email_'.$notify_key.' = '.$notify_status;
            }
            $sql = "UPDATE users SET email = :email {$query_notify} WHERE id = :uid";
            $params = array();
            $params[] = array(':uid', $uid, \PDO::PARAM_INT);
            $params[] = array(':email', $email, \PDO::PARAM_STR);
            $this->postSqlQuery($sql, $params);
            $_SESSION['email']['address'] = $email;
            $msg .= "\r\n\r\n---\r\nС уважением,\r\nколлетив сайта www.lead4crm.ru\r\nE-mail: support@lead4crm.ru\r\nТел.: +7 (499) 704-69-17\r\nБазы 2ГИС официально и быстро!";
            $subject = 'Lead4CRM: Изменены уведомления';
            $headers = "From: noreply@lead4crm.ru\r\n";
            $headers.= "Reply-To: support@lead4crm.ru\r\n";
            $headers.= "X-Mailer: Lead4CRM Email Bot 1.0";
        }
        return mail($email, $subject, $msg, $headers);
    }

    /**
     * Функция авторизации через социальную сеть Facebook.
     */
    private function getFacebookLogin()
    {
        $redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/facebook/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = http_build_query(
            array(
                'client_id'     => $this->conf->provider->facebook->CLIENT_ID,
                'client_secret' => $this->conf->provider->facebook->CLIENT_SECRET,
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect_uri,
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/oauth/access_token?'.$data);
        $access_token = null;
        parse_str($response = curl_exec($curl));
        curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/me?access_token='.$access_token);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res->id, $res->email, 'fb');
    }

    /**
     * Функция авторизации через социальную сеть Вконтакте.
     */
    private function getVkontakteLogin()
    {
        $redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/vkontakte/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = http_build_query(
            array(
                'client_id'     => $this->conf->provider->vkontakte->CLIENT_ID,
                'client_secret' => $this->conf->provider->vkontakte->CLIENT_SECRET,
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect_uri,
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://oauth.vk.com/access_token?'.$data);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res->user_id, $res->email, 'vk');
    }

    /**
     * Функция авторизации через социальный сервис Google.
     */
    private function getGoogleLogin()
    {
        $redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/google-plus/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data =  http_build_query(
            array(
                'client_id'     => $this->conf->provider->{"google-plus"}->CLIENT_ID,
                'client_secret' => $this->conf->provider->{"google-plus"}->CLIENT_SECRET,
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $res = json_decode(curl_exec($curl));
        curl_setopt($curl, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v1/userinfo?access_token='.$res->access_token);
        curl_setopt($curl, CURLOPT_POST, false);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res->id, $res->email, 'gp');
    }

    /**
     * Функция авторизации через социальную сеть Одноклассники.
     */
    private function getOdnoklassnikiLogin()
    {
        $redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/odnoklassniki/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = http_build_query(
            array(
                'client_id'     => $this->conf->provider->odnoklassniki->CLIENT_ID,
                'client_secret' => $this->conf->provider->odnoklassniki->SECRET_KEY,
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://api.odnoklassniki.ru/oauth/token.do');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $res = json_decode(curl_exec($curl));
        $con_param = 'application_key='.$this->conf->provider->odnoklassniki->PUBLIC_KEY.'fields=uid,emailmethod=users.getCurrentUser';
        $ac_ask = $res->access_token.$this->conf->provider->odnoklassniki->SECRET_KEY;
        $md5_ac_ask = md5($ac_ask);
        $sig = $con_param . $md5_ac_ask;
        $md5_sig = md5($sig);
        $data = http_build_query(
            array(
                'application_key' => $this->conf->provider->odnoklassniki->PUBLIC_KEY,
                'method'          => 'users.getCurrentUser',
                'access_token'    => $res->access_token,
                'fields'          => 'uid,email',
                'sig'             => $md5_sig,
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'http://api.ok.ru/fb.do?'.$data);
        curl_setopt($curl, CURLOPT_POST, false);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res->uid, $res->email, 'ok');
    }

    /**
     * Функция авторизации через социальный сервис Mail.Ru.
     */
    private function getMailRuLogin()
    {
        $redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/mailru/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = http_build_query(
            array(
                'client_id'     => $this->conf->provider->mailru->CLIENT_ID,
                'client_secret' => $this->conf->provider->mailru->SECRET_KEY,
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://connect.mail.ru/oauth/token');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $res = json_decode(curl_exec($curl));
        $sig = 'app_id='.$this->conf->provider->mailru->CLIENT_ID.'method=users.getInfosecure=1session_key='.$res->access_token.$this->conf->provider->mailru->SECRET_KEY;
        $md5_sig = md5($sig);
        $data = http_build_query(
            array(
            'app_id'        => $this->conf->provider->mailru->CLIENT_ID,
            'method'        => 'users.getInfo',
            'secure'        => 1,
            'session_key'   => $res->access_token,
            'sig'           => $md5_sig,
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'http://www.appsmail.ru/platform/api?'.$data);
        curl_setopt($curl, CURLOPT_POST, false);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res[0]->uid, $res[0]->email, 'mr');
    }

    /**
     * Функция авторизации через социальный сервис Яндекс.
     */
    private function getYandexLogin()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = http_build_query(
            array(
                'client_id'     => $this->conf->provider->yandex->CLIENT_ID,
                'client_secret' => $this->conf->provider->yandex->CLIENT_SECRET,
                'code'          => $_GET['code'],
                'grant_type'    => 'authorization_code',
            )
        );
        curl_setopt($curl, CURLOPT_URL, 'https://oauth.yandex.ru/token');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $res = json_decode(curl_exec($curl));
        curl_setopt($curl, CURLOPT_URL, 'https://login.yandex.ru/info?oauth_token='.$res->access_token);
        curl_setopt($curl, CURLOPT_POST, false);
        $res = json_decode(curl_exec($curl));
        $this->getDBLogin($res->id, $res->default_email, 'ya');
    }

    /**
     * Приватная функция авторизации/регистрации пользователя в системе Lead4CRM.
     *
     * @param int $userId Идентификатор пользователя в системе oAuth провайдера.
     * @param string $userEmail Адрес электропочты пользователя полученный от oAuth провайдера.
     * @param string $provider Буквенный код oAuth провайдера.
     */
    private function getDBLogin($userId, $userEmail, $provider)
    {
        $uid = $_SESSION['userid'];
        if ($uid) {
            $sql = "UPDATE users SET {$provider} = :userid WHERE id = :uid";
            $param = array();
            $param[] = array(':userid', $userId, \PDO::PARAM_INT);
            $param[] = array(':uid', $uid, \PDO::PARAM_INT);
            $this->getSingleRow($sql, $param);
            header("Location: /cabinet/");
        } else {
            $sql = "SELECT * FROM users WHERE {$provider} = :userid";
            $param = array();
            $param[] = array(':userid', $userId, \PDO::PARAM_INT);
            $user = $this->getSingleRow($sql, $param);
            if ($user['id']) {
                $userid             = $user['id'];
                $contract           = $user['contract2'];
                $email              = $user['email'];
                $email_renewal      = ($user['email_renewal'] == 't') ? true : false;
                $company            = $user['company'];
                $is_admin           = ($user['is_admin'] == 't') ? true : false;
                $apikey             = $user['apikey'];
                $telegram_chat_id   = $user['telegram_chat_id'];
                $telegram_company   = ($user['telegram_company'] == 't') ? true : false;
                $telegram_renewal   = ($user['telegram_renewal'] == 't') ? true : false;
                $telegram_balance   = ($user['telegram_balans'] == 't') ? true : false;
                $icq_uin            = $user['icq_uin'];
                $icq_company        = ($user['icq_company'] == 't') ? true : false;
                $icq_renewal        = ($user['icq_renewal'] == 't') ? true : false;
                $icq_balance        = ($user['icq_balans'] == 't') ? true : false;
                $sms_phone          = $user['sms_phone'];
                $sms_company        = ($user['sms_company'] == 't') ? true : false;
                $sms_renewal        = ($user['sms_renewal'] == 't') ? true : false;
                $sms_balance        = ($user['sms_balans'] == 't') ? true : false;
                $vk                 = $user['vk'];
                $ok                 = $user['ok'];
                $fb                 = $user['fb'];
                $gp                 = $user['gp'];
                $mr                 = $user['mr'];
                $ya                 = $user['ya'];
            } else {
                $state = sha1($_SERVER['HTTP_USER_AGENT'].time());
                $sql = "INSERT INTO users(email, {$provider}, apikey, refid) VALUES (:email, :uid, :state, (SELECT id FROM crm_referals WHERE uid = :refid)) RETURNING id, vk, ok, fb, gp, mr, ya, contract2, email, email_renewal";
                $param = array();
                $param[] = array(':email', $userEmail, \PDO::PARAM_STR);
                $param[] = array(':uid', $userId, \PDO::PARAM_INT);
                $param[] = array(':state', $state, \PDO::PARAM_STR);
                $param[] = array(':refid', $_COOKIE['_refid'], \PDO::PARAM_INT);
                $user = $this->getSingleRow($sql, $param);
                $userid             = $user['id'];
                $contract           = $user['contract2'];
                $email              = $user['email'];
                $email_renewal      = ($user['email_renewal'] == 't') ? true : false;
                $apikey             = $state;
                $vk                 = $user['vk'];
                $ok                 = $user['ok'];
                $fb                 = $user['fb'];
                $gp                 = $user['gp'];
                $mr                 = $user['mr'];
                $ya                 = $user['ya'];
            }
            $_SESSION['userid'] = $userid;
            $_SESSION['contract'] = $contract;
            $_SESSION['company'] = isset($company) ? $company : '';
            $_SESSION['is_admin'] = isset($is_admin) ? $is_admin : false;
            $_SESSION['apikey'] = $apikey;
            $_SESSION['telegram'] = array(
                'chat_id' => isset($telegram_chat_id) ? $telegram_chat_id : '',
                'company' => isset($telegram_company) ? $telegram_company : false,
                'renewal' => isset($telegram_renewal) ? $telegram_renewal : false,
                'balance' => isset($telegram_balance) ? $telegram_balance : false,
            );
            $_SESSION['icq'] = array(
                'uin'     => isset($icq_uin) ? $icq_uin : '',
                'company' => isset($icq_company) ? $icq_company : false,
                'renewal' => isset($icq_renewal) ? $icq_renewal : false,
                'balance' => isset($icq_balance) ? $icq_balance : false,
            );
            $_SESSION['sms'] = array(
                'phone'   => isset($sms_phone) ? $sms_phone : '',
                'company' => isset($sms_company) ? $sms_company : false,
                'renewal' => isset($sms_renewal) ? $sms_renewal : false,
                'balance' => isset($sms_balance) ? $sms_balance : false,
            );
            $_SESSION['email'] = array(
                'address' => $email,
                'renewal' => $email_renewal,
            );
            $_SESSION['provider'] = array(
                'vk' => $vk,
                'ok' => $ok,
                'fb' => $fb,
                'gp' => $gp,
                'mr' => $mr,
                'ya' => $ya,
            );
            $_SESSION['auth'] = true;
            header("Location: /cabinet/");
        }
    }

    /**
     * Функция оформления контактной информации полученной из 2ГИС.
     *
     * @param string $type
     * @param string $json
     * @param null $asString
     * @param null $prefix
     * @param null $suffix
     * @param null $comment
     * @param bool $useAddon
     * @return array|string
     */
    static private function get2GISContact($type, $json, $asString = null, $prefix = null, $suffix = null, $comment = null, $useAddon = false)
    {
        $_return = array();
        if (null === $asString) {
            $asString = true;
        }
        for ($i = 0; $i < count($json['contacts']); $i++) {
            foreach ($json['contacts'][$i]['contacts'] as $contact) {
                if ($contact['type'] == $type) {
                    if (($type == 'phone' || $type == 'fax') && $contact['value']) {
                        $contact['value'] = self::getPhoneConvert($contact['value']);
                    }
                    if ($useAddon && $contact['value']) {
                        if ($prefix && $suffix) {
                            $suffix = ($comment ? $suffix.' '.$contact['comment'] : $suffix);
                            $_r = $prefix.$contact['value'].$suffix;
                        }
                        elseif ($prefix) {
                            $end = ($comment ? ' '.$contact['comment'] : "");
                            $_r = $prefix.$contact['value'].$end;
                        }
                        elseif ($suffix) {
                            $suffix = ($comment ? $suffix.' '.$contact['comment'] : $suffix);
                            $_r = $contact['value'].$suffix;
                        }
                        else {
                            $end = ($comment ? ' '.$contact['comment'] : "");
                            $_r = ($type != 'website' ? $contact['value'].$end : 'http://'.$contact['alias']);
                        }
                    } else {
                        $_r = $contact['value'];
                    }
                    $_return[] = ($type != 'website' ? $_r : 'http://'.$contact['alias']);
                }
            }
        }
        return ($asString ? implode(',', $_return) : $_return);
    }

    /**
     * Функция вычисляет основной вид деятельсноти компании — только один!
     * Эта функция необходима из-за того, что во многих CRM системах вид деятельсности компании можно выбрать
     * только один, поэтому из множества выбирается один наиболее общий и подходящий.
     *
     * @param array $rubrics Массив рубрик компании, по которому предстоит совершить выборку.
     * @return string Название основной рубрики 2ГИС для данной компании.
     */
    private function getMainIndustry($rubrics)
    {
        if (count($rubrics)) {
            $parents = array();
            foreach ($rubrics as $rubric) {
                $sql = "SELECT parent FROM rubrics WHERE name = :rubric";
                $params = array();
                $params[] = array(':rubric', $rubric, \PDO::PARAM_STR);
                $parent = $this->getSingleRow($sql, $params);
                $parent_id1 = $parent['parent'];
                $sql = "SELECT parent FROM rubrics WHERE id = :parent";
                $params = array();
                $params[] = array(':parent', $parent_id1, \PDO::PARAM_INT);
                $parent = $this->getSingleRow($sql, $params);
                $parent_id2 = $parent['parent'];
                if ($parent_id2)
                    $parents[] = $parent_id2;
                else
                    $parents[] = $parent_id1;
            }
            $main_parent = $main_parent2 = array_count_values($parents);
            arsort($main_parent2);
            $params = array();
            foreach ($main_parent2 as $parent_id => $count) {
                if ($count <= 1) {
                    $parent_id = key($main_parent);
                }
                $params[] = array(':parent', $parent_id, \PDO::PARAM_INT);
                break;
            }
            $sql = "SELECT name FROM rubrics WHERE id = :parent";
            $rubric = $this->getSingleRow($sql, $params);
            $name = $rubric['name'];
        } else {
            $name = 'Другое';
        }
        return $name;
    }

    /**
     * Функция генерирует расширенный комментарий по конкретной компании для дальнейшего использования
     * в карточке компании в любой поддерживающий комментацрии CRM системе.
     *
     * @param array $cp Массив данных о компании.
     * @return string Расширенный комментарий.
     */
    static private function bx24Comment($cp)
    {
        $comment = '';
        if (count($cp['rubrics'])) {
            $comment .= '<p><b>Виды деятельности:</b></p><ul>';
            foreach ($cp['rubrics'] as $rubric)
                $comment .= '<li>'.$rubric.'</li>';
            $comment .= '</ul>';
        }
        $url_name = rawurlencode($cp['name']);
        $comment .= "<p><b>Дополнительная информация:</b></p><ul>"
            . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17/routeTab/to/{$cp['lon']}%2C{$cp['lat']}%E2%95%8E{$url_name}?utm_source=profile&utm_medium=route_to&utm_campaign=partnerapi' target='_blank'>Проложить маршрут до {$cp['name']}</a></li>"
            . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17/routeTab/from/{$cp['lon']}%2C{$cp['lat']}%E2%95%8E{$url_name}?utm_source=profile&utm_medium=route_from&utm_campaign=partnerapi' target='_blank'>Проложить маршрут от {$cp['name']}</a></li>"
            . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/entrance/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=entrance&utm_campaign=partnerapi' target='_blank'>Показать вход</a></li>"
            . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/photos/{$cp['id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=photo&utm_campaign=partnerapi' target='_blank'>Фотографии {$cp['name']}</a></li>"
            . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/flamp/{$cp['id']}/callout/firms-{$cp['id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=review&utm_campaign=partnerapi' target='_blank'>Отзывы о {$cp['name']}</a></li>";
        $additional_info = "<li><a href='{$cp['bookle_url']}?utm_source=profile&utm_medium=booklet&utm_campaign=partnerapi' target='_blank'>Услуги и цены {$cp['name']}</a></li>";
        if ($cp['bookle_url'])
            $comment .= $additional_info;
        return $comment;
    }

    /**
     * Функция генерирует полный адрес компании в одну строку. Требуется для некоторых CRM систем.
     *
     * @param array $json Массив данных с адресной информацией о компании.
     * @return string Полный адрес в одну строку.
     */
    static private function getFullAddress($json)
    {
        if ($json['additional_info']['office'])
            $fullAddr = array('г. '.$json['city_name'], $json['address'], $json['additional_info']['office']);
        else
            $fullAddr = array('г. '.$json['city_name'], $json['address']);
        return implode(', ', $fullAddr);
    }

    /**
     * Приватная функция для выполнения запросов к базе без получения в результате запроса ответа.
     * Наиболее полезна для запросов UPDATE и DELETE, без использования RETURNING.
     *
     * @param string $sql Строка содержащая SQL-запрос.
     * @param array $values Двумерный массив содержащий 3 значения.
     */
    private function postSqlQuery($sql, array $values)
    {
        $db = $this->db;
        try {
            $sth = $db->prepare($sql);
            if (count($values) > 0) {
                foreach ($values as $value) {
                    list($p, $v, $t) = $value;
                    $sth->bindValue($p, $v, $t);
                }
            }
            $sth->execute();
        } catch (\PDOException $e) {
            $this->exception($e);
        }
    }

    /**
     * Приватная функция получения данных из БД, но не более одной строки.
     *
     * @param string $sql Строка содержащая SQL-запрос.
     * @param array $values Двумерный массив содержащий 3 значения.
     * @return array Массив с результатом данных.
     */
    private function getSingleRow($sql, array $values)
    {
        $db = $this->db;
        $row = array();
        try {
            $sth = $db->prepare($sql);
            if (count($values) > 0) {
                foreach ($values as $value) {
                    list($p, $v, $t) = $value;
                    $sth->bindValue($p, $v, $t);
                }
            }
            $sth->execute();
            $row = $sth->fetch();
        } catch (\PDOException $e) {
            $this->exception($e);
        }
        return $row;
    }

    /**
     * Приватная функция получения из БД более одной строки.
     *
     * @param string $sql Строка содержащая SQL-запрос.Строка содержащая SQL-запрос.
     * @param array $values Двумерный массив содержащий 3 значения.Двумерный массив содержащий 3 значения.
     * @return array Массив с результатом данных. Массив с результатом данных.
     */
    private function getMultipleRows($sql, array $values)
    {
        $db = $this->db;
        $rows = array();
        try {
            $sth = $db->prepare($sql);
            if (count($values) > 0) {
                foreach ($values as $value) {
                    list($p, $v, $t) = $value;
                    $sth->bindValue($p, $v, $t);
                }
            }
            $sth->execute();
            $rows = $sth->fetchAll();
        } catch (\PDOException $e) {
            $this->exception($e);
        }
        return $rows;
    }

    /**
     * Функция принудительно отдает указанный файл.
     *
     * @param string $date Дата файла (входит в состав имени файла).
     * @param string $type Расширение файла.
     * @param string $affix Идентификатор файла — название CRM системы.
     */
    static private function getFile($date, $type, $affix)
    {
        $filename = __DIR__.'/ucf/'.$_SESSION['userid'].'/2GIS_Base_'.$affix.'_'.$date.'.'.$type;
        if (ob_get_level())
            ob_end_clean();
        switch ($type) {
            case 'csv':
                $ct = 'text/csv';
                break;

            case 'xls':
                $ct = 'application/vnd.ms-excel';
                break;

            case 'xlsx':
                $ct = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;

            default:
                $ct = 'application/octet-stream';
        }
        header('Content-Description: File Transfer');
        header('Content-Type: '.$ct);
        header('Content-Disposition: attachment; filename='.basename($filename));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        if ($fd = fopen($filename, 'rb')) {
            while (!feof($fd)) {
                print fread($fd, 1024);
            }
            fclose($fd);
        }
    }

    static private function getPhoneConvert($phone)
    {
        if (preg_match('/(\d)(\d{3})(\d{7})/', $phone, $matches))
            return $matches[1].'-'.$matches[2].'-'.$matches[3];
        return $phone;
    }

    /**
     * Приватная функция конвертирует сумму из численного формата в строчный, т.е. представляет её в письменном формате.
     *
     * @param float $num Сумму цифрами. Разделитель дробной части - точка.
     * @return string Сумма прописью.
     */
    static private function summ2str($num)
    {
        $nul='ноль';
        $ten=array(
            array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
            array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
        );
        $a20=array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
        $tens=array(2=>'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
        $hundred=array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
        $unit=array( // Units
            array('копейка' ,'копейки' ,'копеек',	 1),
            array('рубль'   ,'рубля'   ,'рублей'    ,0),
            array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
            array('миллион' ,'миллиона','миллионов' ,0),
            array('миллиард','милиарда','миллиардов',0),
        );
        //
        list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
        $out = array();
        if (intval($rub) > 0) {
            foreach(str_split($rub, 3) as $uk => $v) { // by 3 symbols
                if (!intval($v)) continue;
                $uk = sizeof($unit) - $uk - 1; // unit key
                $gender = $unit[$uk][3];
                list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
                // mega-logic
                $out[] = $hundred[$i1]; # 1xx-9xx
                if ($i2 > 1) $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3]; # 20-99
                else $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
                // units without rub & kop
                if ($uk > 1) $out[] = self::morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
            } //foreach
        }
        else $out[] = $nul;
        $out[] = self::morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
        $out[] = $kop . ' ' . self::morph($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
        return trim(preg_replace('/ {2,}/', ' ', join(' ',$out)));
    }

    /**
     * Приватная морфологическая функция правильного склонения.
     * Вычисляет падеж в зависимости от числа.
     *
     * @param int $n Целочисленное значение.
     * @param string $f1 "1 копейка"
     * @param string $f2 "2 копейки"
     * @param string $f5 "5 копеек"
     * @return string Возвращает правильное падежное значение.
     */
    static private function morph($n, $f1, $f2, $f5)
    {
        $n = abs(intval($n)) % 100;
        if ($n>10 && $n<20) return $f5;
        $n = $n % 10;
        if ($n>1 && $n<5) return $f2;
        if ($n==1) return $f1;
        return $f5;
    }

    /**
     * Приватная функция выполняет преобразование первого символа строки в верхний регистр.
     * По сути это также функция что и ucfirst, но делает это с мультибайтовой строкой.
     *
     * @param string $str Строка для преобразования
     * @param string $encoding Кодировака
     * @return string Преобразованная строка
     */
    static private function mb_ucfirst($str, $encoding = 'UTF-8')
    {
        $str = mb_ereg_replace('^[\ ]+', '', $str);
        $str = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) .
            mb_substr($str, 1, mb_strlen($str), $encoding);
        return $str;
    }

    /**
     * Функция формирует дату с полными русскими названиями месяцев в правильном падеже.
     *
     * @return string Текущая дата в формате: ДД ММММ ГГГГ
     */
    static private function getDateRUS()
    {
        $date = explode('.',date('d.m.Y'));
        switch ($date[1]) {
            case 1: $m = 'января'; break;
            case 2: $m = 'февраля'; break;
            case 3: $m = 'марта'; break;
            case 4: $m = 'апреля'; break;
            case 5: $m = 'мая'; break;
            case 6: $m = 'июня'; break;
            case 7: $m = 'июля'; break;
            case 8: $m = 'августа'; break;
            case 9: $m = 'сентября'; break;
            case 10: $m = 'октября'; break;
            case 11: $m = 'ноября'; break;
            case 12: $m = 'декабря'; break;
            default: $m = 'плятоня';
        }
        return $date[0].' '.$m.' '.$date[2];
    }

    /**
     * Функция прерывает выполнение скрипта и выводит соотщение об ошибке с указанием номера ошибки и
     * сообщением самой ошибки. В данному случает служит для отловли ошибок при работе с PDO.
     *
     * @param object $event Объект класса исключения
     */
    private function exception($event)
    {
        $json_message = json_encode(
            array(
                'error' => $event->getCode(),
                'message' => $event->getMessage(),
                'line' => $event->getLine(),
                'trace' => $event->getTrace(),
            )
        );
        die($json_message);
    }
}