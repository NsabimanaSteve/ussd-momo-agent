<?php
file_put_contents('/home/steve.nsabimana/public_html/debug.log', date('Y-m-d H:i:s') . ' POST: ' . json_encode($_POST) . ' GET: ' . json_encode($_GET) . ' RAW: ' . file_get_contents('php://input') . "\n", FILE_APPEND);

class Database
{
    private static $dbh = NULL;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$dbh === NULL)
        {
            self::$dbh = new PDO(
                "mysql:host=localhost;port=3306;dbname=mobileapps_2026B_steve_nsabimana",
                "steve.nsabimana",
                "Nsabimana2@"
            );
            self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$dbh;
    }
}

class SessionManager
{
    public function getStep($session_ID)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM sessionmanager WHERE session_ID = :id");
        $stmt->execute([":id" => $session_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row)
        {
            return 0;
        }
        $count = 0;
        foreach ($row as $k => $v)
        {
            if ($v !== NULL && $k != 'session_ID')
            {
                $count++;
            }
        }
        return $count + 1;
    }

    public function create($session_ID)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO sessionmanager (session_ID) VALUES (:id)");
        $stmt->execute([":id" => $session_ID]);
    }

    public function set($session_ID, $col, $val)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE sessionmanager SET $col = :v WHERE session_ID = :id");
        $stmt->execute([":v" => $val, ":id" => $session_ID]);
    }

    public function get($session_ID, $col)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT $col FROM sessionmanager WHERE session_ID = :id");
        $stmt->execute([":id" => $session_ID]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r[$col] : null;
    }

    public function clear($session_ID)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM sessionmanager WHERE session_ID = :id");
        $stmt->execute([":id" => $session_ID]);
    }
}

$msisdn    = $_POST['phoneNumber'] ?? $_POST['msisdn'] ?? '';
$sessionId = $_POST['sessionId'] ?? $_POST['sequenceID'] ?? '';
$data      = trim($_POST['text'] ?? $_POST['data'] ?? '');
$isAT      = isset($_POST['sessionId']);

$session = new SessionManager();
$step = $session->getStep($sessionId);

$res = "";
$end = false;

// global cancel
if (($data === '0' || $data === '7') && $step > 0)
{
    $res = "Session cancelled.\r\nThank you for using MoMo Agent Service.";
    $end = true;
    $session->clear($sessionId);
}

// step 0 - welcome
elseif ($step == 0)
{
    $session->create($sessionId);
    $res = "Welcome to MoMo Agent Service!\nPlease select what you would like to do:\n\n1. Cash-In\n2. Cash-Out\n3. Transfer Money\n9. More Options";
    
}

// step 1 - main menu selection
elseif ($step == 1)
{
    if (!in_array($data, ['1','2','3','4','5','9','0','7']))
    {
        $res = "Invalid option.\r\nPlease try again:\r\n\r\n1. Cash-In\r\n2. Cash-Out\r\n3. Transfer Money\r\n9. More Options";
        $session->clear($sessionId);
        $session->create($sessionId);
    }
    elseif ($data == '9')
    {
        $res = "MoMo Agent Service\r\n\r\n4. Check Balance\r\n5. My Account\r\n7. Cancel";
        $session->clear($sessionId);
        $session->create($sessionId);
    }
    else
    {
        $session->set($sessionId, 'T1', $data);
        if ($data == '1' || $data == '2')
        {
            $type = ($data == '1') ? "Cash-In" : "Cash-Out";
            $res = "$type\r\n\r\nPlease enter the customer's\r\nphone number:\r\n\r\n0. Cancel";
        }
        elseif ($data == '3')
        {
            $res = "Transfer Money\r\n\r\n1. MoMo Wallet\r\n2. Bank Account\r\n0. Cancel";
        }
        elseif ($data == '4')
        {
            $res = "Check Balance\r\n\r\nPlease enter your\r\nagent PIN:\r\n\r\n0. Cancel";
        }
        elseif ($data == '5')
        {
            $res = "My Account\r\n\r\n1. Transaction History\r\n2. Agent Profile\r\n3. Float Balance\r\n0. Cancel";
        }
    }
}

// step 2
elseif ($step == 2)
{
    $T1 = $session->get($sessionId, 'T1');

    if ($T1 == '3' && !in_array($data, ['1','2','0','7']))
    {
        $res = "Invalid option.\r\nPlease try again:\r\n\r\n1. MoMo Wallet\r\n2. Bank Account\r\n0. Cancel";
    }
    elseif ($T1 == '5' && !in_array($data, ['1','2','3','0','7']))
    {
        $res = "Invalid option.\r\nPlease try again:\r\n\r\n1. Transaction History\r\n2. Agent Profile\r\n3. Float Balance\r\n0. Cancel";
    }
    elseif (($T1 == '1' || $T1 == '2') && !preg_match('/^[0-9]{10}$/', $data))
    {
        $res = "Invalid phone number.\r\nPlease enter a valid\r\n10-digit phone number:\r\n\r\n0. Cancel";
    }
    elseif ($T1 == '4' && !preg_match('/^[0-9]{4}$/', $data))
    {
        $res = "Invalid PIN.\r\nPlease enter a valid\r\n4-digit PIN:\r\n\r\n0. Cancel";
    }
    else
    {
        $session->set($sessionId, 'T2', $data);
        if ($T1 == '3')
        {
            if ($data == '1')
            {
                $res = "MoMo Transfer\r\n\r\nPlease enter the recipient's\r\nphone number:\r\n\r\n0. Cancel";
            }
            else
            {
                $res = "Bank Transfer\r\n\r\nPlease enter the bank\r\naccount number:\r\n\r\n0. Cancel";
            }
        }
        elseif ($T1 == '4')
        {
            $res = "Check Balance\r\n\r\nPlease select account type:\r\n1. Float Account\r\n2. Commission Account\r\n0. Cancel";
        }
        elseif ($T1 == '5')
        {
            if ($data == '1')
            {
                $res = "Transaction History\r\n\r\nPlease select period:\r\n1. Today\r\n2. Last 7 Days\r\n3. Last 30 Days\r\n0. Cancel";
            }
            elseif ($data == '2')
            {
                $res = "Agent Profile\r\n\r\nPlease enter your\r\nagent PIN to continue:\r\n\r\n0. Cancel";
            }
            elseif ($data == '3')
            {
                $res = "Float Balance\r\n\r\nPlease enter your\r\nagent PIN to continue:\r\n\r\n0. Cancel";
            }
        }
        else
        {
            $res = "Please enter the transaction\r\namount (GHS):\r\n\r\n0. Cancel";
        }
    }
}

// step 3
elseif ($step == 3)
{
    $T1 = $session->get($sessionId, 'T1');

    if (($T1 == '1' || $T1 == '2') && !is_numeric($data))
    {
        $res = "Invalid amount.\r\nPlease enter a valid\r\namount in GHS:\r\n\r\n0. Cancel";
    }
    elseif (($T1 == '1' || $T1 == '2') && floatval($data) <= 0)
    {
        $res = "Amount must be greater than 0.\r\nPlease enter a valid amount:\r\n\r\n0. Cancel";
    }
    else
    {
        $session->set($sessionId, 'T3', $data);
        if ($T1 == '3')
        {
            $res = "Please enter the transaction\r\namount (GHS):\r\n\r\n0. Cancel";
        }
        elseif ($T1 == '4')
        {
            $res = "Check Balance\r\n\r\nPlease confirm your request:\r\n1. Confirm\r\n2. Cancel";
        }
        elseif ($T1 == '5')
        {
            $T2 = $session->get($sessionId, 'T2');
            if ($T2 == '1')
            {
                $res = "Transaction History\r\n\r\nPlease confirm your request:\r\n1. Confirm\r\n2. Cancel";
            }
            else
            {
                $res = "Please confirm your request:\r\n1. Confirm\r\n2. Cancel";
            }
        }
        else
        {
            $T2 = $session->get($sessionId, 'T2');
            $res = "Transaction Summary\r\n\r\nCustomer: $T2\r\nAmount: GHS " . number_format($data, 2) . "\r\n\r\n1. Confirm\r\n2. Cancel";
        }
    }
}

// step 4
elseif ($step == 4)
{
    $T1 = $session->get($sessionId, 'T1');

    if (in_array($T1, ['1','2','3']) && !in_array($data, ['1','2','0','7']))
    {
        $res = "Invalid option.\r\nPlease select:\r\n1. Confirm\r\n2. Cancel";
    }
    elseif ($data == '2')
    {
        $res = "Transaction cancelled.\r\nThank you for using\r\nMoMo Agent Service.";
        $end = true;
        $session->clear($sessionId);
    }
    else
    {
        $session->set($sessionId, 'T4', $data);
        if ($T1 == '3')
        {
            $T3 = $session->get($sessionId, 'T3');
            $res = "Transaction Summary\r\n\r\nRecipient: $T3\r\nAmount: GHS " . number_format($data, 2) . "\r\n\r\n1. Confirm\r\n2. Cancel";
        }
        elseif ($T1 == '4')
        {
            $res = "Check Balance\r\n\r\nPlease select currency:\r\n1. GHS\r\n2. USD\r\n0. Cancel";
        }
        elseif ($T1 == '5')
        {
            $res = "My Account\r\n\r\nPlease enter your\r\nagent PIN to confirm:\r\n\r\n0. Cancel";
        }
        else
        {
            $res = "Confirmed!\r\n\r\nPress any key to\r\nview your receipt.";
        }
    }
}

// step 5
elseif ($step == 5)
{
    $T1 = $session->get($sessionId, 'T1');
    $session->set($sessionId, 'T5', $data);

    if ($T1 == '3')
    {
        if ($data == '2')
        {
            $res = "Transaction cancelled.\r\nThank you for using\r\nMoMo Agent Service.";
            $end = true;
            $session->clear($sessionId);
        }
        else
        {
            $res = "Confirmed!\r\n\r\nPress any key to\r\nview your receipt.";
        }
    }
    elseif ($T1 == '4')
    {
        $ref = 'BAL' . rand(100, 999);
        $session->set($sessionId, 'T6', $ref);
        $T2 = $session->get($sessionId, 'T2');
        $type = ($T2 == '1') ? "Float Account" : "Commission Account";
        $res = "Balance Enquiry\r\n\r\nAccount: $type\r\nBalance: GHS " . number_format(rand(1000, 9999), 2) . "\r\nDate: " . date('d/m/Y') . "\r\n\r\nThank you.";
        $end = true;
        $session->clear($sessionId);
    }
    elseif ($T1 == '5')
    {
        $ref = 'ACC' . rand(100, 999);
        $session->set($sessionId, 'T6', $ref);
        $res = "Request Successful!\r\n\r\nProcessed successfully.\r\nRef: $ref\r\nDate: " . date('d/m/Y') . "\r\n\r\nThank you.";
        $end = true;
        $session->clear($sessionId);
    }
    else
    {
        $T2 = $session->get($sessionId, 'T2');
        $T3 = $session->get($sessionId, 'T3');
        $ref = 'TXN' . rand(100000, 999999);
        $session->set($sessionId, 'T6', $ref);
        $type = ($T1 == '1') ? "Cash-In" : "Cash-Out";
        $res = "$type Successful!\r\n\r\nCustomer: $T2\r\nAmount: GHS " . number_format($T3, 2) . "\r\nRef: $ref\r\nDate: " . date('d/m/Y') . "\r\n\r\nThank you.";
        $end = true;
        $session->clear($sessionId);
    }
}

// step 6 - transfer receipt
elseif ($step == 6)
{
    $T3 = $session->get($sessionId, 'T3');
    $T4 = $session->get($sessionId, 'T4');
    $ref = 'TXN' . rand(100000, 999999);
    $session->set($sessionId, 'T6', $ref);
    $res = "Transfer Successful!\r\n\r\nRecipient: $T3\r\nAmount: GHS " . number_format($T4, 2) . "\r\nRef: $ref\r\nDate: " . date('d/m/Y') . "\r\n\r\nThank you.";
    $end = true;
    $session->clear($sessionId);
}
else
{
    $res = "Session error.\r\nPlease dial again.";
    $end = true;
    $session->clear($sessionId);
}

if ($isAT)
{
    echo ($end ? "END " : "CON ") . $res;
}
else
{
    echo json_encode(["message" => $res]);
}
?>
