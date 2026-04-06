<?php
file_put_contents('/home/steve.nsabimana/public_html/debug.log', date('Y-m-d H:i:s') . ' POST: ' . json_encode($_POST) . ' GET: ' . json_encode($_GET) . ' RAW: ' . file_get_contents('php://input') . "\n", FILE_APPEND);

class Database {
    private static $dbh = NULL;
    private $conn = null;
    private function __construct() {
        $this->conn = new PDO("mysql:host=localhost;port=3306;dbname=mobileapps_2026B_steve_nsabimana","steve.nsabimana","Nsabimana2@");
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public static function getInstance() {
        if (NULL === self::$dbh) { self::$dbh = (new Database())->conn; }
        return self::$dbh;
    }
}
class SessionManager {
    public function sessionManager($session_ID) {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("SELECT (COUNT(session_ID)+COUNT(T1)+COUNT(T2)+COUNT(T3)+COUNT(T4)+COUNT(T5)+COUNT(T6)) AS counter FROM sessionmanager WHERE session_ID = :session_ID");
            $stmt->bindParam(":session_ID", $session_ID);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res !== FALSE) { return $res['counter']; }
        } catch (PDOException $e) { return NULL; }
    }
    public function IdentifyUser($session_ID) {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("INSERT INTO sessionmanager (session_ID) VALUES (:session_ID)");
            $stmt->bindParam(":session_ID", $session_ID);
            $stmt->execute();
            if ($stmt->rowCount() > 0) { return TRUE; }
        } catch (PDOException $e) { return FALSE; }
    }
    public function UpdateTransactionType($session_ID, $col, $trans_type) {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("UPDATE sessionmanager SET ".$col." = :trans_type WHERE session_ID = :session_ID");
            $stmt->execute([":session_ID" => $session_ID, ":trans_type" => $trans_type]);
            if ($stmt->rowCount() > 0) { return TRUE; }
        } catch (PDOException $e) { return FALSE; }
    }
    public function GetTransactionType($session_ID, $col) {
        $db = Database::getInstance();
        try {
            $stmt = $db->query("SELECT $col FROM sessionmanager WHERE session_ID = '$session_ID'");
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res !== FALSE) { return $res[$col]; }
        } catch (PDOException $e) { return NULL; }
    }
    public function DeleteSession($session_ID) {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("DELETE FROM sessionmanager WHERE session_ID = :session_ID");
            $stmt->bindParam(":session_ID", $session_ID);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) { return FALSE; }
    }
}

$msisdn     = isset($_POST['phoneNumber'])  ? $_POST['phoneNumber']  : (isset($_POST['msisdn'])     ? $_POST['msisdn']     : '');
$sequenceID = isset($_POST['sessionId'])    ? $_POST['sessionId']    : (isset($_POST['sequenceID']) ? $_POST['sequenceID'] : '');
$data       = isset($_POST['text'])         ? $_POST['text']         : (isset($_POST['data'])       ? $_POST['data']       : '');
$isAT       = isset($_POST['sessionId']);

$currentTimestamp = date('YmdHis');
$myObj = new stdClass();
$myObj->msisdn = $msisdn;
$myObj->sequenceID = $sequenceID;
$myObj->timestamp = $currentTimestamp;

$session = new SessionManager();
$counter = $session->sessionManager($sequenceID);

if ($counter == 0) {
    $session->IdentifyUser($sequenceID);
    $myObj->message = "Welcome Agent\r\n*899# MoMo Agent\r\n\r\n1. Cash-in\r\n2. Cash-out\r\n3. Transfer money\r\n4. Check balance\r\n5. My account";
    $myObj->continueFlag = 0;
} elseif ($counter == 1) {
    $session->UpdateTransactionType($sequenceID,'T1',$data);
    if ($data=='1') { $myObj->message = "Cash-in\r\n\r\nEnter customer\r\nphone number:"; }
    elseif ($data=='2') { $myObj->message = "Cash-out\r\n\r\nEnter customer\r\nphone number:"; }
    elseif ($data=='3') { $myObj->message = "Transfer money\r\n\r\n1. MoMo wallet\r\n2. Bank account"; }
    elseif ($data=='4') { $myObj->message = "Check balance\r\n\r\nEnter your agent PIN:"; }
    elseif ($data=='5') { $myObj->message = "My account\r\n\r\n1. Transaction history\r\n2. Agent profile\r\n3. Float balance"; }
    $myObj->continueFlag = 0;
} elseif ($counter == 2) {
    $T1 = $session->GetTransactionType($sequenceID,'T1');
    $session->UpdateTransactionType($sequenceID,'T2',$data);
    if ($T1=='3') {
        if ($data=='1') { $myObj->message = "MoMo Transfer\r\n\r\nEnter recipient\r\nphone number:"; }
        else { $myObj->message = "Bank Transfer\r\n\r\nEnter bank\r\naccount number:"; }
    } else {
        $myObj->message = "Enter amount\r\n(GHS):";
    }
    $myObj->continueFlag = 0;
} elseif ($counter == 3) {
    $T1 = $session->GetTransactionType($sequenceID,'T1');
    $session->UpdateTransactionType($sequenceID,'T3',$data);
    if ($T1=='3') {
        $myObj->message = "Enter amount\r\n(GHS):";
    } else {
        $T2 = $session->GetTransactionType($sequenceID,'T2');
        $myObj->message = "Summary\r\n\r\nCustomer: $T2\r\nAmount: GHS $data\r\n\r\n1. Confirm\r\n2. Cancel";
    }
    $myObj->continueFlag = 0;
} elseif ($counter == 4) {
    $T1 = $session->GetTransactionType($sequenceID,'T1');
    $session->UpdateTransactionType($sequenceID,'T4',$data);
    if ($T1=='3') {
        $T3 = $session->GetTransactionType($sequenceID,'T3');
        $myObj->message = "Summary\r\n\r\nRecipient: $T3\r\nAmount: GHS $data\r\n\r\n1. Confirm\r\n2. Cancel";
    } else {
        $myObj->message = "Confirmed!\r\n\r\nPress any key\r\nto get receipt.";
    }
    $myObj->continueFlag = 0;
} elseif ($counter == 5) {
    $T1 = $session->GetTransactionType($sequenceID,'T1');
    $session->UpdateTransactionType($sequenceID,'T5',$data);
    if ($T1=='3') {
        if ($data=='2') { $myObj->message = "Transaction cancelled.\r\nDial again."; $myObj->continueFlag = 1; $session->DeleteSession($sequenceID); }
        else { $myObj->message = "Confirmed!\r\n\r\nPress any key\r\nto get receipt."; $myObj->continueFlag = 0; }
    } else {
        $T2 = $session->GetTransactionType($sequenceID,'T2');
        $T3 = $session->GetTransactionType($sequenceID,'T3');
        $ref = 'TXN'.rand(100000,999999);
        $session->UpdateTransactionType($sequenceID,'T6',$ref);
        if ($T1=='1') { $myObj->message = "Cash-in Successful!\r\n\r\nCustomer: $T2\r\nAmount: GHS $T3\r\nRef: $ref\r\nDate: ".date('d/m/Y')."\r\n\r\nThank you."; }
        elseif ($T1=='2') { $myObj->message = "Cash-out Successful!\r\n\r\nCustomer: $T2\r\nAmount: GHS $T3\r\nRef: $ref\r\nDate: ".date('d/m/Y')."\r\n\r\nThank you."; }
        $myObj->continueFlag = 1;
        $session->DeleteSession($sequenceID);
    }
} elseif ($counter == 6) {
    $T1 = $session->GetTransactionType($sequenceID,'T1');
    $T3 = $session->GetTransactionType($sequenceID,'T3');
    $T4 = $session->GetTransactionType($sequenceID,'T4');
    $ref = 'TXN'.rand(100000,999999);
    $session->UpdateTransactionType($sequenceID,'T6',$ref);
    $myObj->message = "Transfer Sent!\r\n\r\nTo: $T3\r\nAmount: GHS $T4\r\nRef: $ref\r\nDate: ".date('d/m/Y')."\r\n\r\nSession ended.";
    $myObj->continueFlag = 1;
    $session->DeleteSession($sequenceID);
} else {
    $myObj->message = "Session error.\r\nDial again.";
    $myObj->continueFlag = 1;
    $session->DeleteSession($sequenceID);
}

if ($isAT) {
    $prefix = ($myObj->continueFlag == 0) ? 'CON ' : 'END ';
    echo $prefix . $myObj->message;
} else {
    echo json_encode($myObj);
}
?>