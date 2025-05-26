<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is crew member
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'crew') {
    header('Location: login.php');
    exit;
}

$crew_id = $_SESSION['user']['crew_id'];
$crew_name = $_SESSION['user']['name'];

if ($_POST && $_POST['action'] === 'add') {
    $type = trim($_POST['type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    
    if (empty($type) || $amount <= 0 || empty($date)) {
        $_SESSION['error'] = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        $work = loadData('work');
        
        $newWork = [
            'id' => generateId(),
            'type' => $type,
            'crew_id' => $crew_id,
            'amount' => $amount,
            'date' => $date,
            'notes' => $notes,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
            'added_by' => 'crew',
            'added_by_name' => $crew_name,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $work[] = $newWork;
        saveData('work', $work);
        $_SESSION['message'] = 'Travail ajouté avec succès.';
    }
}

header('Location: crew_dashboard.php');
exit;
?>