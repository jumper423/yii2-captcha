<?php

namespace jumper423\Captcha;

use yii\base\Component;
use yii\base\Exception;

/**
 * Распознавание капчи
 *
 * Class Captcha
 * @package common\components
 */
class Captcha extends Component
{
    /**
     * Сервис на который будем загружать капчу
     * @var string
     */
    public $domain = "rucaptcha.com";
    /**
     * Путь до парки временного хранения капч (необходимо если будем передавать ссылку на капчу)
     * @var string
     */
    public $pathTmp = 'captcha';
    /**
     * Ваш API key
     * @var string
     */
    public $apiKey;
    /**
     * false(commenting OFF), true(commenting ON)
     * @var bool
     */
    public $isVerbose = true;
    /**
     * Таймаут проверки ответа
     * @var int
     */
    public $requestTimeout = 5;
    /**
     * Максимальное время ожидания ответа
     * @var int
     */
    public $maxTimeout = 120;
    /**
     * 0 OR 1 - капча из двух или более слов
     * @var int
     */
    public $isPhrase = 0;
    /**
     * 0 OR 1 - регистр ответа важен
     * @var int
     */
    public $isRegSense = 0;
    /**
     * 0 OR 1 OR 2 OR 3 - 0 = параметр не задействован (значение по умолчанию) 1 = капча состоит только из цифр 2 = Капча состоит только из букв 3 = Капча состоит либо только из цифр, либо только из букв.
     * @var int
     */
    public $isNumeric = 0;
    /**
     * 0 если не ограничено, иначе обозначает минимальную длину ответа
     * @var int
     */
    public $minLen = 0;
    /**
     * 0 если не ограничено, иначе обозначает минимальную длину ответа
     * @var int
     */
    public $maxLen = 0;
    /**
     * 0 OR 1 OR 2 0 = параметр не задействован (значение по умолчанию) 1 = капча на кириллице 2 = капча на латинице
     * @var int
     */
    public $language = 0;
    /**
     * Ошибка
     * @var null|string
     */
    private $error = null;
    /**
     * Результат
     * @var null|string
     */
    private $result = null;

    /**
     * Описание ошибок
     * @var array
     */
    private $errors = [
        'ERROR_NO_SLOT_AVAILABLE' => 'Нет свободных работников в данный момент, попробуйте позже либо повысьте свою максимальную ставку здесь',
        'ERROR_ZERO_CAPTCHA_FILESIZE' => 'Размер капчи которую вы загружаете менее 100 байт',
        'ERROR_TOO_BIG_CAPTCHA_FILESIZE' => 'Ваша капча имеет размер более 20 килобайт',
        'ERROR_ZERO_BALANCE' => 'Нулевой либо отрицательный баланс',
        'ERROR_IP_NOT_ALLOWED' => 'Запрос с этого IP адреса с текущим ключом отклонен. Пожалуйста смотрите раздел управления доступом по IP',
        'ERROR_CAPTCHA_UNSOLVABLE' => 'Не смог разгадать капчу',
        'ERROR_BAD_DUPLICATES' => 'Функция 100% распознавания не сработала и-за лимита попыток',
        'ERROR_NO_SUCH_METHOD' => 'Вы должны слать параметр method в вашем запросе к API, изучите документацию',
        'ERROR_IMAGE_TYPE_NOT_SUPPORTED' => 'Невозможно определить тип файла капчи, принимаются только форматы JPG, GIF, PNG',
        'ERROR_KEY_DOES_NOT_EXIST' => 'Использован несуществующий key',
        'ERROR_WRONG_USER_KEY' => 'Неверный формат параметра key, должно быть 32 символа',
        'ERROR_WRONG_ID_FORMAT' => 'Неверный формат ID каптчи. ID должен содержать только цифры',
        'ERROR_WRONG_FILE_EXTENSION' => 'Ваша каптча имеет неверное расширение, допустимые расширения jpg,jpeg,gif,png',
    ];

    /**
     * Запуск распознавания капчи
     * @param string $filename Путь до файла или ссылка на него
     * @return bool
     */
    public function run($filename)
    {
        $this->result = null;
        $this->error = null;
        try {
            if (strpos($filename, 'http://') !== false || strpos($filename, 'https://') !== false) {
                $current = file_get_contents($filename);
                if ($current) {
                    $path = \Yii::getAlias($this->pathTmp) . '/' . \Yii::$app->security->generateRandomString();
                    if (file_put_contents($path, $current)) {
                        $filename = $path;
                    } else {
                        throw new Exception("Нет доступа для записи файла");
                    }
                } else {
                    throw new Exception("Файл {$filename} не загрузился");
                }
            } elseif (!file_exists($filename)) {
                throw new Exception("Файл {$filename} не найден");
            }
            $postData = [
                'method' => 'post',
                'key' => $this->apiKey,
                'file' => '@' . $filename,
                'phrase' => $this->isPhrase,
                'regsense' => $this->isRegSense,
                'numeric' => $this->isNumeric,
                'min_len' => $this->minLen,
                'max_len' => $this->maxLen,
                'language' => $this->language,
                'soft_id' => 423,
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://{$this->domain}/in.php");
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("CURL вернул ошибку: " . curl_error($ch));
            }
            curl_close($ch);
            $this->setError($result);
            list(, $captcha_id) = explode("|", $result);
            $waitTime = 0;
            sleep($this->requestTimeout);
            while (true) {
                $result = file_get_contents("http://{$this->domain}/res.php?key={$this->apiKey}&action=get&id={$captcha_id}");
                $this->setError($result);
                if ($result == "CAPCHA_NOT_READY") {
                    $waitTime += $this->requestTimeout;
                    if ($waitTime > $this->maxTimeout) {
                        break;
                    }
                    sleep($this->requestTimeout);
                } else {
                    $ex = explode('|', $result);
                    if (trim($ex[0]) == 'OK') {
                        $this->result = trim($ex[1]);
                        return true;
                    }
                }
            }
            throw new Exception('Лимит времени превышен');
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * Результат
     * @return null|string
     */
    public function result()
    {
        return $this->result;
    }

    /**
     * Ошибка
     * @return null|string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Проверка на то произошла ли ошибка
     * @param $error
     * @throws Exception
     */
    private function setError($error)
    {
        if (strpos($error, 'ERROR') !== false) {
            if (array_key_exists($error, $this->errors)) {
                throw new Exception($this->errors[$error]);
            } else {
                throw new Exception($error);
            }
        }
    }
}