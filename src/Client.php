<?php

declare(strict_types=1);

/*
 * This file is part of capitalist.net api.
 * (с) 2015 Capitalist.
 */

namespace aLkRicha\Capitalist;

/**
 * Смотрите актуальную документацию с примерами по адресу:
 * Read actual documentation at.
 *
 * https://capitalist.net/developers/api
 *
 *
 * Class Client
 * File:    Client.php
 *
 * For questions, help, comments, discussion, etc., please
 * send e-mail to support@capitalist.net
 *
 * @see https://www.capitalist.net
 *
 * @copyright 2015 Capitalist
 *
 * @version 0.7
 */
class Client
{
    const OPERATION_GET_TOKEN                       = 'get_token';
    const OPERATION_IMPORT_BATCH                    = 'import_batch';
    const OPERATION_GET_BATCH_INFO                  = 'get_batch_info';
    const OPERATION_REGISTER_INVITEE                = 'register_invitee';
    const OPERATION_GET_HISTORY                     = 'get_documents_history';
    const OPERATION_GET_HISTORY_EXT                = 'get_documents_history_ext';
    const OPERATION_GET_ACCOUNTS                    = 'get_accounts';
    const OPERATION_CREATE_ACCOUNT                  = 'create_account';
    const OPERATION_GET_DOCUMENT_FEE                = 'get_document_fee';
    const OPERATION_IMPORT_BATCH_ADV                = 'import_batch_advanced';
    const OPERATION_PROCESS_BATCH                   = 'process_batch';
    const OPERATION_GET_CASHIN_REQUISITES           = 'get_cashin_requisites';
    const OPERATION_REGISTRATION_EMAIL_CONFIRM      = 'registration_email_confirm';
    const OPERATION_PASSWORD_RECOVERY               = 'password_recovery';
    const OPERATION_PASSWORD_RECOVERY_GENERATE_CODE = 'password_recovery_generate_code';
    const OPERATION_GET_EMAIL_VERIFICATION_CODE     = 'profile_get_verification_code';
    const OPERATION_IS_VERIFIED_ACCOUNT             = 'is_verified_account';
    const OPERATION_ADD_NOTIFICATION                = 'add_payment_notification';
    const OPERATION_DOCUMENTS_SEARCH                = 'documents_search';

    const FORMAT_CSV      = 'csv';
    const FORMAT_JSON     = 'json';
    const FORMAT_JSONLITE = 'json-lite';

    const EMAIL_CONFIRM_TYPE_ACTIVATION   = 1;
    const EMAIL_CONFIRM_TYPE_CHANGE       = 0;

    const NOTIFICATION_CHANNEL_EMAIL   = 'EMAIL';
    const NOTIFICATION_CHANNEL_SMS     = 'SMS';

    const NOTIFICATION_LANG_RU   = 'ru';
    const NOTIFICATION_LANG_EN   = 'en';

    public $debugLog = false;

    /**
     * HTTP Заголовки крайнего ответа.
     *
     * @var array
     */
    protected $lastResponseHeaders = [];

    private $_API_url = null;

    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var string */
    private $token;
    /** @var int */
    private $lastErrorCode;
    /** @var string */
    private $lastErrorMessage;
    /** @var string */
    private $lastResult;

    /** @var string */
    private $apiAuthUser = '';
    /** @var string */
    private $apiAuthPassword = '';

    /**
     * Формат ответов - csv, json, json-lite.
     *
     * @var string */
    private $responseFormat = null;

    public function __construct($APIurl)
    {
        $this->_API_url = $APIurl;
        $p              = parse_url($APIurl);
        if (isset($p['user'])) {
            $this->apiAuthUser = $p['user'];
        }
        if (isset($p['pass'])) {
            $this->apiAuthPassword = $p['pass'];
        }
    }

    /**
     * Инициализация сессии.
     */
    public function startSession($username, $password)
    {
        $this->setUsername($username);
        $this->setPassword($this->encryptPassword($this->getSecurityAttributes(), $password));
    }

    /**
     * Шифрование пароля.
     */
    public function encryptPassword($attributes, $password)
    {
        $encrypter = new Encrypter($attributes['modulus'], $attributes['exponent']);

        return $encrypter->encrypt($password);
    }

    /**
     * Получение атрибутов шифрования и сессионного ключа (токена).
     *
     * Операция API: get_token
     *
     * @throws CapitalistException
     *
     * @return array
     */
    public function getSecurityAttributes()
    {
        if (!$this->sendPost($this::OPERATION_GET_TOKEN)) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        switch ($this->getLastResponseFormat()) {
            case self::FORMAT_JSON:
                $response = $this->getJsonResult();
                $result   = $response['data'];
                break;
            case self::FORMAT_JSONLITE:
                $response = $this->getJsonResult();
                $result   = [
                    'token'    => $response['data'][0][1],
                    'modulus'  => $response['data'][0][2],
                    'exponent' => $response['data'][0][3],
                ];
                break;
            default:
            case self::FORMAT_CSV:
                $response = $this->getCsvResult();
                $result   = [
                    'token'    => $response[0][1],
                    'modulus'  => $response[0][2],
                    'exponent' => $response[0][3],
                ];
            break;
        }

        $this->token = $result['token'];

        return $result;
    }

    /**
     * Отправка кода подтверждения для восстановления пароля.
     *
     * Операция API: password_recovery_generate_code
     *
     * @param string $identity Имя пользователя или e-mail
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    public function sendPasswordRecoveryCode($identity)
    {
        if (!$this->sendPost($this::OPERATION_PASSWORD_RECOVERY_GENERATE_CODE, [
            'identity' => $identity,
        ], true)) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Отправка кода подтверждения для смены имейла или активации аккаунта.
     *
     * Операция API: profile_get_verification_code
     *
     * @param string $login       Имя пользователя
     * @param int    $regCodeType Тип кода верификации
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    public function sendEmailConfirmationCode($login, $regCodeType = self::EMAIL_CONFIRM_TYPE_ACTIVATION)
    {
        if (!$this->sendPost($this::OPERATION_GET_EMAIL_VERIFICATION_CODE, [
            'login' => $login,
            'reg_code' => $regCodeType,
        ], true)) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * @param string $username
     * @param string $newPassword
     * @param string $confirmationCode
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    public function passwordRecovery($username, $newPassword, $confirmationCode)
    {
        $this->setUsername($username);
        $encryptedPassword = $this->encryptPassword($this->getSecurityAttributes(), $newPassword);

        if (!$this->sendPost($this::OPERATION_PASSWORD_RECOVERY, [
            'login'              => $username,
            'encrypted_password' => $encryptedPassword,
            'code'               => $confirmationCode,
        ], true)) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return $this
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    public function hasError()
    {
        return 0 !== $this->getLastErrorCode();
    }

    /**
     * @return int
     */
    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    /**
     * @return string
     */
    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    /**
     * @param string $lastErrorMessage
     *
     * @return $this
     */
    public function setLastErrorMessage($lastErrorMessage)
    {
        $this->lastErrorMessage = $lastErrorMessage;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * @return mixed
     */
    public function getJsonResult()
    {
        return json_decode($this->lastResult, true);
    }

    /**
     * Строку ответа getLastResult превращает в массив в зависимости от формата ответа.
     *
     * @throws CapitalistException
     *
     * @return array
     */
    public function getLastResultAsArray()
    {
        switch ($this->getLastResponseFormat()) {
            case self::FORMAT_CSV:
                return $this->getCsvResult();
                break;
            case self::FORMAT_JSON:
                return $this->getJsonResult();
                break;
            case self::FORMAT_JSONLITE:
                return $this->getJsonliteResult();
                break;
            default:
                throw new CapitalistException('Unknown response format.');
                break;
        }
    }

    /**
     * @return array
     */
    public function getCsvResult()
    {
        $array = [];
        foreach ((array) explode("\n", $this->lastResult) as $line) {
            $array[] = explode(';', $line);
        }

        return $array;
    }

    /**
     * @return mixed
     */
    public function getJsonliteResult()
    {
        return json_decode($this->lastResult, true);
    }

    /**
     * @param string $lastResult
     *
     * @return $this
     */
    public function setLastResult($lastResult)
    {
        $this->lastResult = $lastResult;

        return $this;
    }

    /**
     * @param int $lastErrorCode
     *
     * @return $this
     */
    public function setLastErrorCode($lastErrorCode)
    {
        $this->lastErrorCode = (int) $lastErrorCode;

        return $this;
    }

    /**
     * Операция API: import_batch.
     *
     * @param string $batchContent
     * @param string $signature
     * @param string $accountRUR
     * @param string $accountEUR
     * @param string $accountUSD
     *
     * @throws CapitalistException
     *
     * @deprecated
     */
    public function pushBatch($batchContent, $signature, $accountRUR, $accountEUR, $accountUSD)
    {
        throw new CapitalistException('API method import_batch is deprecated. Please use import_batch_advanced instead.');
    }

    /**
     * Операция API: import_batch_advanced.
     *
     * @param string $batchContent
     * @param string $accountRUR
     * @param string $accountEUR
     * @param string $accountUSD
     * @param string $verificationType
     * @param string $verificationData
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function pushBatchAdvanced($batchContent, $accountRUR, $accountEUR, $accountUSD, $accountBTC = null, $verificationType, $verificationData = null)
    {
        if (!$this->sendPost($this::OPERATION_IMPORT_BATCH_ADV, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'account_RUR' => $accountRUR,
            'account_EUR' => $accountEUR,
            'account_USD' => $accountUSD,
            'account_BTC' => $accountBTC,
            'batch' => $batchContent,
            'verification_type' => $verificationType,
            'verification_data' => $verificationData,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: add_payment_notification.
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function addPaymentNotification($document, $channel = self::NOTIFICATION_CHANNEL_EMAIL, $address, $language = self::NOTIFICATION_LANG_RU)
    {
        if (!$this->sendPost($this::OPERATION_ADD_NOTIFICATION, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'document' => $document,
            'channel' => $channel,
            'address' => $address,
            'language' => $language,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: process_batch.
     *
     * @param string $batchId
     * @param string $verificationType
     * @param string $verificationData
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function processBatch($batchId, $verificationType, $verificationData)
    {
        if (!$this->sendPost($this::OPERATION_PROCESS_BATCH, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'batch_id' => $batchId,
            'verification_type' => $verificationType,
            'verification_data' => $verificationData,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Операция API: documents_search.
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function documentsSearch($customNumber = null, $beginDate = null, $endDate = null)
    {
        if (!$this->sendPost($this::OPERATION_DOCUMENTS_SEARCH, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'customNumber' => $customNumber,
            'beginDate' => $beginDate,
            'endDate' => $endDate,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_batch_info.
     *
     * @param string $batchId
     * @param int    $pageSize
     * @param int    $offset
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function getBatchRecords($batchId, $pageSize = 100, $offset = 0)
    {
        if (!$this->sendPost($this::OPERATION_GET_BATCH_INFO, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'batch_id' => $batchId,
            'page_size' => $pageSize,
            'start_offset' => $offset,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: register_invitee.
     *
     * @param string      $username
     * @param string      $email
     * @param string      $nickname
     * @param bool|string $mobile   (optional)
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function registerInvitee($username, $email, $nickname, $mobile = false)
    {
        if (!$this->sendPost($this::OPERATION_REGISTER_INVITEE, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'invitee_login' => $username,
            'invitee_email' => $email,
            'invitee_nickname' => $nickname,
            'invitee_mobile' => $mobile,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_cashin_requisites.
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function getCashInRequisites()
    {
        if (!$this->sendPost($this::OPERATION_GET_CASHIN_REQUISITES, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_documents_history.
     *
     * @param string $account
     * @param string $from     (optional)
     * @param string $to       (optional)
     * @param string $docState (optional)
     * @param int    $limit    (optional)
     * @param int    $page     (optional)
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function getHistory($account, $from = null, $to = null, $docState = null, $limit = 30, $page = 1)
    {
        if (!$this->sendPost($this::OPERATION_GET_HISTORY, [
            'encrypted_password' => $this->getPassword(),
            'login' => $this->getUsername(),
            'token' => $this->token,
            'account' => $account,
            'period_from' => $from,
            'period_to' => $to,
            'document_state' => $docState,
            'limit' => $limit,
            'page' => $page,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_documents_history_test.
     *
     * @param string $account
     * @param string $from     (optional)
     * @param string $to       (optional)
     * @param string $docState (optional)
     * @param int    $limit    (optional)
     * @param int    $page     (optional)
     *
     * @throws CapitalistException
     *
     * @internal param string $token
     *
     * @return string
     */
    public function getHistoryExt($account, $from = null, $to = null, $docState = null, $limit = 30, $page = 1)
    {
        if (!$this->sendPost($this::OPERATION_GET_HISTORY_EXT, [
            'encrypted_password' => $this->getPassword(),
            'login' => $this->getUsername(),
            'token' => $this->token,
            'account' => $account,
            'period_from' => $from,
            'period_to' => $to,
            'document_state' => $docState,
            'limit' => $limit,
            'page' => $page,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: registration_email_confirm.
     *
     * @param string $codeFromEmail
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    public function registrationEmailConfirm($codeFromEmail)
    {
        if (!$this->sendPost($this::OPERATION_REGISTRATION_EMAIL_CONFIRM, [
            'code' => $codeFromEmail,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_accounts.
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function getUserAccounts()
    {
        if (!$this->sendPost($this::OPERATION_GET_ACCOUNTS, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Проверка, верифицрован ли владелец счета.
     *
     * Операция API: is_verified_account
     *
     * @param string $account Номер счета, например, R0978541
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    public function isVerifiedUserByAccountNumber($account)
    {
        if (!$this->sendPost($this::OPERATION_IS_VERIFIED_ACCOUNT, [
            'account' => $account,
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
        ])) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Вызов операции с произвольными параметрами.
     *
     * @param $operation
     * @param array $params
     * @param bool  $guest
     *
     * @throws \aLkRicha\Capitalist\CapitalistException
     *
     * @return string
     */
    public function callOperation($operation, $params = [], $guest = false)
    {
        $p = array_merge($guest ? [] : [
            'encrypted_password' => $this->getPassword(),
            'token'              => $this->token,
        ], $params);

        if (!$this->sendPost($operation, $p)) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: create_account.
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function createAccount($currency, $title)
    {
        if (!$this->sendPost($this::OPERATION_CREATE_ACCOUNT, [
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'account_name' => $title,
            'account_currency' => $currency,
        ])
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * Операция API: get_document_fee.
     *
     * @param string $docType
     * @param array  $paymentDetails
     *
     * @throws CapitalistException
     *
     * @return string
     */
    public function getDocumentFee($docType, $paymentDetails)
    {
        if (!$this->sendPost($this::OPERATION_GET_DOCUMENT_FEE, array_merge([
            'encrypted_password' => $this->getPassword(),
            'token' => $this->token,
            'document_type' => $docType,
        ], $paymentDetails))
        ) {
            throw new CapitalistException(sprintf('Error: %s: %s', $this->getLastErrorCode(), $this->getLastErrorMessage()));
        }

        return $this->getLastResult();
    }

    /**
     * @return string
     */
    public function getResponseFormat()
    {
        return $this->responseFormat;
    }

    /**
     * @param string $responseFormat
     *
     * @return $this
     */
    public function setResponseFormat($responseFormat)
    {
        $this->responseFormat = $responseFormat;

        return $this;
    }

    /**
     * Формат последнего ответа, пришедший в заголовке ответа,
     * при нормальном функционировании, всегда совпадае с x-response-format переданным в заголовках запроса.
     * Если не совпадают, значит что-то пошло не так.
     *
     * @return string
     */
    public function getLastResponseFormat()
    {
        $headers = $this->getLastResponseHeaders();

        return $headers['x-response-format'] ?? null;
    }

    /**
     * @return array
     */
    public function getLastResponseHeaders()
    {
        return $this->lastResponseHeaders;
    }

    /**
     * @param array $lastResponseHeaders
     *
     * @return $this
     */
    public function setLastResponseHeaders($lastResponseHeaders)
    {
        $this->lastResponseHeaders = $lastResponseHeaders;

        return $this;
    }

    /**
     * @param $string
     *
     * @return array
     */
    public function parseHttpHeaders($string)
    {
        $headers = [];
        foreach ((array) explode("\r\n", $string) as $line) {
            $kv = explode(': ', $line, 2);

            if (isset($kv[0]) && trim($kv[0])) {
                $headers[trim($kv[0])] = $kv[1] ?? null;
            }
        }

        return $headers;
    }

    public function log($string)
    {
        if ($this->debugLog) {
            printf("\n[%s] debug log: %s\n", date('d.m.Y H:i:s'), $string);
        }
    }

    /**
     * Service functions block.
     */

    /**
     * Вызов API.
     *
     * @param string $operation
     * @param array  $params
     * @param bool   $anonymous
     *
     * @throws CapitalistException
     *
     * @return mixed
     */
    protected function sendPost($operation, $params = [], $anonymous = false)
    {
        $data = array_merge(['operation' => $operation], $params);

        if (!$anonymous) {
            $data = array_merge($data, ['login' => $this->getUsername()]);
        }

        $ch = curl_init($this->_API_url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => true,     // return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => '',       // handle all encodings
            CURLOPT_USERAGENT      => 'Client', // who am i
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false,     // Disabled SSL Cert checks
        ];
        curl_setopt_array($ch, $options);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        if ($this->apiAuthUser) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->apiAuthUser.($this->apiAuthPassword ? ':'.$this->apiAuthPassword : ''));
        }

        if ($this->getResponseFormat()) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-response-format: '.$this->getResponseFormat(),
            ]);
        }

        $response = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->setLastResponseHeaders($this->parseHttpHeaders(substr($response, 0, $header_size)));
        $result = substr($response, $header_size);

        if (!$result) {
            throw new CapitalistException('No result found');
        }

        // $this->log('Response headers: '. implode("\n\r", $this->getLastResponseHeaders()));
        $this->log('Response body: '.$result);

        return $this->setLastResult($result)->validateResult($result);
    }

    /**
     * Примитивная проверка, что ответ от сервера похож на заданный в заголовке.
     *
     * @param $result
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    protected function validateResult($result)
    {
        switch ($this->getLastResponseFormat()) {
            default:
            case self::FORMAT_CSV:
                return $this->validateCsvResult($result);
                break;
            case self::FORMAT_JSON:
            case self::FORMAT_JSONLITE:
                return $this->validateJsonResult($result);
                break;
        }
    }

    protected function validateJsonResult($result)
    {
        try {
            $array = json_decode($result, true);
        } catch (CapitalistException $e) {
            throw new CapitalistException('Invalid response.');
        }
        if (!$array || !isset($array['code']) || !isset($array['message']) || !isset($array['data'])) {
            throw new CapitalistExceptionn('Invalid response.');
        }
        $this->setLastErrorCode($array['code']);
        $this->setLastErrorMessage($array['message']);

        return !$this->hasError();
    }

    /**
     * Примитивная проверка, что ответ от сервера похож на CSV и обработка кода ошибки API.
     *
     * @param $result
     *
     * @throws CapitalistException
     *
     * @return bool
     */
    protected function validateCsvResult($result)
    {
        $lines = explode("\n", $result);
        if (!preg_match('/^\d+\;.+$/', $lines[0])) {
            throw new CapitalistException('Invalid response.');
        }
        $firstline = explode(';', $lines[0]);
        $errorCode = $firstline[0];
        $this->setLastErrorCode($errorCode);
        if (!$errorCode) {
            $this->setLastErrorMessage(false);
        } else {
            $this->setLastErrorMessage($firstline[1] ?? '');
        }

        return !$this->hasError();
    }
}
