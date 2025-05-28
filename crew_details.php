<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkPermission('edit');

// Get crew member ID
$crew_id = $_GET['id'] ?? '';
if (empty($crew_id)) {
    header('Location: crew.php');
    exit;
}

// Load data
$crew = loadData('crew');
$work = loadData('work');
$advances = loadData('advances');
$payments = loadData('payments');

// Find the specific crew member
$crewMember = null;
foreach ($crew as $member) {
    if ($member['id'] === $crew_id) {
        $crewMember = $member;
        break;
    }
}

if (!$crewMember) {
    header('Location: crew.php');
    exit;
}

// Filter data for this crew member (before form processing)
$crewWork = array_filter($work, function($w) use ($crew_id) {
    return $w['crew_id'] === $crew_id;
});

$crewAdvances = array_filter($advances, function($a) use ($crew_id) {
    return $a['crew_id'] === $crew_id;
});

$crewPayments = array_filter($payments, function($p) use ($crew_id) {
    return $p['crew_id'] === $crew_id;
});

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_advance') {
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d H:i:s');
        
        if ($amount <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } else {
            $newAdvance = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'amount' => $amount,
                'reason' => $reason,
                'date' => $date,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $advances[] = $newAdvance;
            saveData('advances', $advances);
            
            // Automatically add to charges
            $charges = loadData('charges');
            $newCharge = [
                'id' => generateId(),
                'type' => 'Avance - ' . htmlspecialchars($crewMember['name']),
                'amount' => $amount,
                'date' => $date,
                'description' => 'Avance: ' . $reason,
                'category' => 'Salaires et Avances',
                'created_at' => date('Y-m-d H:i:s'),
                'crew_id' => $crew_id,
                'advance_id' => $newAdvance['id']
            ];
            $charges[] = $newCharge;
            saveData('charges', $charges);
            
            $message = 'Avance ajoutée avec succès et enregistrée dans les charges.';
            
            // Reload data
            $advances = loadData('advances');
        }
    } elseif ($action === 'add_payment') {
        $salary_month = $_POST['salary_month'] ?? '';
        $bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
        $net_payment = floatval($_POST['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($net_payment <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } elseif (empty($salary_month)) {
            $error = 'Le mois de salaire est obligatoire.';
        } else {
            // Calculate period dates
            $year = substr($salary_month, 0, 4);
            $month = substr($salary_month, 5, 2);
            $period_start = $year . '-' . $month . '-01';
            $last_day = date('t', strtotime($period_start));
            $period_end = $year . '-' . $month . '-' . $last_day;
            
            // Calculate actual values for the period
            $base_salary = floatval($crewMember['salary_base'] ?? 0);
            
            // Get work revenue for period
            $work_revenue = 0;
            foreach ($crewWork as $work) {
                $work_date = substr($work['date'], 0, 10);
                if ($work_date >= $period_start && $work_date <= $period_end) {
                    $work_revenue += floatval($work['amount']);
                }
            }
            
            // Get advances for period
            $advances_deducted = 0;
            foreach ($crewAdvances as $advance) {
                if ($advance['status'] === 'pending') {
                    $advance_date = substr($advance['date'], 0, 10);
                    if ($advance_date >= $period_start && $advance_date <= $period_end) {
                        $advances_deducted += floatval($advance['amount']);
                    }
                }
            }
            
            // Calculate bonus
            $bonus_threshold = 2000;
            $eligible_bonus = max(0, $work_revenue - $bonus_threshold);
            $bonus_amount = ($eligible_bonus * $bonus_percentage) / 100;
            
            $newPayment = [
                'id' => generateId(),
                'crew_id' => $crew_id,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'base_salary' => $base_salary,
                'work_revenue' => $work_revenue,
                'bonus_percentage' => $bonus_percentage,
                'bonus_threshold' => $bonus_threshold,
                'eligible_bonus' => $eligible_bonus,
                'bonus_amount' => $bonus_amount,
                'remaining_salary' => $base_salary,
                'advances_deducted' => $advances_deducted,
                'net_payment' => $net_payment,
                'notes' => $notes,
                'payment_date' => $payment_date,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $payments[] = $newPayment;
            saveData('payments', $payments);
            
            // Automatically add salary to charges for the salary period month
            $charges = loadData('charges');
            $salaryCharge = [
                'id' => generateId(),
                'type' => 'Salaire - ' . htmlspecialchars($crewMember['name']),
                'amount' => $net_payment,
                'date' => $period_start, // Use salary period month, not payment date
                'description' => 'Salaire pour ' . date('F Y', strtotime($salary_month)) . ' (Base: ' . number_format($base_salary, 3) . ' TND, Avances: ' . number_format($advances_deducted, 3) . ' TND, Bonus: ' . number_format($bonus_amount, 3) . ' TND)',
                'category' => 'Salaires et Avances',
                'created_at' => date('Y-m-d H:i:s'),
                'crew_id' => $crew_id,
                'payment_id' => $newPayment['id'],
                'salary_month' => $salary_month
            ];
            $charges[] = $salaryCharge;
            saveData('charges', $charges);
            
            $message = 'Paiement enregistré avec succès et ajouté aux charges.';
            
            // Mark advances as deducted
            foreach ($advances as &$advance) {
                if ($advance['crew_id'] === $crew_id && $advance['status'] === 'pending') {
                    $advance_date = substr($advance['date'], 0, 10);
                    if ($advance_date >= $period_start && $advance_date <= $period_end) {
                        $advance['status'] = 'deducted';
                        $advance['deducted_at'] = date('Y-m-d H:i:s');
                    }
                }
            }
            saveData('advances', $advances);
            
            // Reload data
            $payments = loadData('payments');
            $advances = loadData('advances');
        }
    }
}

// Get selected month for statistics (default to current month)
$selectedMonth = $_GET['stats_month'] ?? date('Y-m');

// Calculate statistics
$totalWork = array_sum(array_column($crewWork, 'amount'));

// Today's revenue
$today = date('Y-m-d');
$todayWork = array_filter($crewWork, function($w) use ($today) {
    return date('Y-m-d', strtotime($w['date'])) === $today;
});
$todayRevenue = array_sum(array_column($todayWork, 'amount'));

// Current month revenue
$thisMonth = date('Y-m');
$thisMonthWork = array_filter($crewWork, function($w) use ($thisMonth) {
    return date('Y-m', strtotime($w['date'])) === $thisMonth;
});
$thisMonthRevenue = array_sum(array_column($thisMonthWork, 'amount'));

// Selected month revenue
$selectedMonthWork = array_filter($crewWork, function($w) use ($selectedMonth) {
    return date('Y-m', strtotime($w['date'])) === $selectedMonth;
});
$selectedMonthRevenue = array_sum(array_column($selectedMonthWork, 'amount'));

// This month's advances only (not total)
$thisMonthAdvances = array_filter($crewAdvances, function($a) use ($thisMonth) {
    return date('Y-m', strtotime($a['date'])) === $thisMonth;
});
$thisMonthAdvancesAmount = array_sum(array_column($thisMonthAdvances, 'amount'));

// Check which months have already been paid
$paidMonths = [];
foreach ($crewPayments as $payment) {
    if (isset($payment['period_start'])) {
        $paidMonth = date('Y-m', strtotime($payment['period_start']));
        $paidMonths[] = $paidMonth;
    }
}

// Determine available months for payment
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));
$availableForPayment = [];

// Can pay for last month if not already paid
if (!in_array($lastMonth, $paidMonths)) {
    $availableForPayment[] = $lastMonth;
}

// Can pay for months before last month if not already paid
for ($i = 2; $i <= 12; $i++) {
    $month = date('Y-m', strtotime("-$i month"));
    if (!in_array($month, $paidMonths)) {
        $availableForPayment[] = $month;
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="crew.php">Équipe</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($crewMember['name']) ?></li>
                        </ol>
                    </nav>
                    <h1 class="h3">Détails de <?= htmlspecialchars($crewMember['name']) ?></h1>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addAdvanceModal">
                        <i class="fas fa-hand-holding-usd"></i> Nouvelle Avance
                    </button>
                    <button type="button" class="btn btn-success" 
                            <?php if (empty($availableForPayment)): ?>
                                disabled title="Aucun mois disponible pour paiement"
                            <?php else: ?>
                                data-bs-toggle="modal" data-bs-target="#addPaymentModal"
                            <?php endif; ?>>
                        <i class="fas fa-money-bill-wave"></i> Nouveau Paiement
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Month Selector for Statistics -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0">Statistiques de Revenus</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" class="d-flex">
                                <input type="hidden" name="id" value="<?= $crew_id ?>">
                                <select name="stats_month" class="form-select me-2" onchange="this.form.submit()">
                                    <?php
                                    for ($i = 0; $i < 12; $i++) {
                                        $month = date('Y-m', strtotime("-$i months"));
                                        $monthName = date('F Y', strtotime($month . '-01'));
                                        $selected = ($month === $selectedMonth) ? 'selected' : '';
                                        echo "<option value='$month' $selected>$monthName</option>";
                                    }
                                    ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($todayRevenue, 2) ?> TND</h4>
                            <p class="card-text">Aujourd'hui</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($selectedMonthRevenue, 2) ?> TND</h4>
                            <p class="card-text"><?= date('F Y', strtotime($selectedMonth . '-01')) ?></p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($totalWork, 2) ?> TND</h4>
                            <p class="card-text">Total Revenus</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($thisMonthAdvancesAmount, 2) ?> TND</h4>
                            <p class="card-text">Avances ce Mois</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hand-holding-usd fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= number_format($totalPayments, 3) ?> TND</h4>
                            <p class="card-text">Total Paiements</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informations Personnelles</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6"><strong>Nom:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['name']) ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Téléphone:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['phone'] ?? '-') ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Email:</strong></div>
                        <div class="col-sm-6"><?= htmlspecialchars($crewMember['email'] ?? '-') ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Salaire de Base:</strong></div>
                        <div class="col-sm-6"><?= number_format($crewMember['salary_base'] ?? 0, 3) ?> TND</div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>% Bonus:</strong></div>
                        <div class="col-sm-6"><?= number_format($crewMember['bonus_percentage'] ?? 0, 1) ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for different sections -->
    <ul class="nav nav-tabs" id="crewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="work-tab" data-bs-toggle="tab" data-bs-target="#work" 
                    type="button" role="tab">Travaux Récents</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="advances-tab" data-bs-toggle="tab" data-bs-target="#advances" 
                    type="button" role="tab">Avances</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" 
                    type="button" role="tab">Paiements</button>
        </li>
    </ul>

    <div class="tab-content" id="crewTabsContent">
        <!-- Work Tab -->
        <div class="tab-pane fade show active" id="work" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewWork)): ?>
                        <p class="text-muted">Aucun travail enregistré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="crew-work-tbody">
                                    <!-- Work entries will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Crew Work -->
                        <nav aria-label="Crew work pagination" id="crew-work-pagination" style="display: none;">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <!-- Pagination buttons will be generated by JavaScript -->
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Advances Tab -->
        <div class="tab-pane fade" id="advances" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewAdvances)): ?>
                        <p class="text-muted">Aucune avance enregistrée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Motif</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($crewAdvances, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    foreach ($crewAdvances as $advance): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($advance['date'])) ?></td>
                                            <td class="fw-bold text-warning"><?= number_format($advance['amount'], 3) ?> TND</td>
                                            <td><?= htmlspecialchars($advance['reason']) ?></td>
                                            <td>
                                                <?php if ($advance['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">En Attente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Déduite</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payments Tab -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($crewPayments)): ?>
                        <p class="text-muted">Aucun paiement enregistré.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Mois Payé</th>
                                        <th>Date Paiement</th>
                                        <th>Montant</th>
                                        <th>% Bonus</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    usort($crewPayments, function($a, $b) {
                                        $dateA = $a['payment_date'] ?? $a['created_at'];
                                        $dateB = $b['payment_date'] ?? $b['created_at'];
                                        return strtotime($dateB) - strtotime($dateA);
                                    });
                                    
                                    foreach ($crewPayments as $payment): ?>
                                        <tr style="cursor: pointer;" onclick="showPaymentDetails('<?= $payment['id'] ?>')">
                                            <td class="fw-bold">
                                                <?php 
                                                if (isset($payment['period_start'])) {
                                                    echo date('F Y', strtotime($payment['period_start']));
                                                } else {
                                                    echo '<span class="text-muted">Non spécifié</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($payment['payment_date'] ?? $payment['created_at'])) ?></td>
                                            <td class="fw-bold text-success"><?= number_format($payment['net_payment'] ?? 0, 3) ?> TND</td>
                                            <td><?= number_format($payment['bonus_percentage'] ?? 0, 1) ?>%</td>
                                            <td><?= htmlspecialchars($payment['notes'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Advance Modal -->
<div class="modal fade" id="addAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Avance - <?= htmlspecialchars($crewMember['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_advance">
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant (TND) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motif *</label>
                        <input type="text" class="form-control" id="reason" name="reason" 
                               placeholder="Motif de l'avance" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="datetime-local" class="form-control" id="date" name="date" 
                               value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Ajouter Avance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Paiement - <?= htmlspecialchars($crewMember['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_payment">
                    
                    <!-- Period Selection -->
                    <div class="mb-3">
                        <label for="salary_month" class="form-label">Mois de Salaire *</label>
                        <?php if (empty($availableForPayment)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun mois disponible pour paiement. Tous les mois éligibles ont déjà été payés.
                            </div>
                            <select class="form-control" disabled>
                                <option>Aucun mois disponible</option>
                            </select>
                        <?php else: ?>
                            <select class="form-control" id="salary_month" name="salary_month" required onchange="calculateSalary()">
                                <option value="">Sélectionner un mois</option>
                                <?php foreach ($availableForPayment as $month): ?>
                                    <option value="<?= $month ?>"><?= date('F Y', strtotime($month . '-01')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Seuls les mois non payés sont disponibles (pas le mois actuel)</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bonus_percentage" class="form-label">Pourcentage Bonus (%)</label>
                        <input type="number" class="form-control" id="bonus_percentage" name="bonus_percentage" 
                               step="0.1" min="0" max="100" value="<?= $crewMember['bonus_percentage'] ?? 0 ?>"
                               oninput="calculateSalary()">
                    </div>
                    
                    <!-- Salary Calculation Display -->
                    <div class="card bg-light mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Détail du Calcul</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-8">Salaire de base:</div>
                                <div class="col-4 text-end" id="base_salary_display"><?= number_format($crewMember['salary_base'] ?? 0, 3) ?> TND</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">Avances déduites:</div>
                                <div class="col-4 text-end text-danger" id="advances_display">0.000 TND</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">Chiffre d'affaires période:</div>
                                <div class="col-4 text-end" id="revenue_display">0.000 TND</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">CA éligible bonus (>2000 TND):</div>
                                <div class="col-4 text-end" id="eligible_bonus_display">0.000 TND</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">Montant bonus (<span id="bonus_rate_display">0</span>%):</div>
                                <div class="col-4 text-end text-success" id="bonus_amount_display">0.000 TND</div>
                            </div>
                            <hr>
                            <div class="row fw-bold">
                                <div class="col-8">Total à payer:</div>
                                <div class="col-4 text-end text-primary" id="total_payment_display">0.000 TND</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Montant Final (TND) *</label>
                        <input type="number" class="form-control" id="payment_amount" name="amount" 
                               step="0.001" min="0" required readonly>
                        <small class="text-muted">Calculé automatiquement</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Date de Paiement</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="notes" rows="3" 
                                  placeholder="Notes sur ce paiement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer Paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store crew data for JavaScript calculations
const crewData = <?= json_encode($crewMember) ?>;
const workData = <?= json_encode(array_values($crewWork)) ?>;
const advancesData = <?= json_encode(array_values($crewAdvances)) ?>;

// Real-time salary calculation
function calculateSalary() {
    const salaryMonth = document.getElementById('salary_month').value;
    const bonusPercentage = parseFloat(document.getElementById('bonus_percentage').value) || 0;
    
    if (!salaryMonth) {
        return;
    }
    
    // Get first and last day of selected month
    const year = salaryMonth.split('-')[0];
    const month = salaryMonth.split('-')[1];
    const periodStart = year + '-' + month + '-01';
    const lastDay = new Date(year, month, 0).getDate();
    const periodEnd = year + '-' + month + '-' + lastDay.toString().padStart(2, '0');
    
    // Calculate work revenue for the period
    let periodRevenue = 0;
    workData.forEach(work => {
        const workDate = work.date.substring(0, 10); // Extract date part
        if (workDate >= periodStart && workDate <= periodEnd) {
            periodRevenue += parseFloat(work.amount) || 0;
        }
    });
    
    // Calculate advances for the period
    let periodAdvances = 0;
    advancesData.forEach(advance => {
        if (advance.status === 'pending') {
            const advanceDate = advance.date.substring(0, 10); // Extract date part
            if (advanceDate >= periodStart && advanceDate <= periodEnd) {
                periodAdvances += parseFloat(advance.amount) || 0;
            }
        }
    });
    
    // Salary calculation
    const baseSalary = parseFloat(crewData.salary_base) || 0;
    const bonusThreshold = 2000;
    const eligibleBonus = Math.max(0, periodRevenue - bonusThreshold);
    const bonusAmount = (eligibleBonus * bonusPercentage) / 100;
    const totalPayment = baseSalary - periodAdvances + bonusAmount;
    
    // Update display
    document.getElementById('base_salary_display').textContent = formatCurrency(baseSalary);
    document.getElementById('advances_display').textContent = formatCurrency(periodAdvances);
    document.getElementById('revenue_display').textContent = formatCurrency(periodRevenue);
    document.getElementById('eligible_bonus_display').textContent = formatCurrency(eligibleBonus);
    document.getElementById('bonus_rate_display').textContent = bonusPercentage.toFixed(1);
    document.getElementById('bonus_amount_display').textContent = formatCurrency(bonusAmount);
    document.getElementById('total_payment_display').textContent = formatCurrency(totalPayment);
    document.getElementById('payment_amount').value = totalPayment.toFixed(3);
}

function formatCurrency(amount) {
    return amount.toFixed(3) + ' TND';
}

// Initialize calculation when modal opens
document.getElementById('addPaymentModal').addEventListener('shown.bs.modal', function() {
    calculateSalary();
});

// Initialize tabs
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate when page loads
    setTimeout(calculateSalary, 100);
    
    // Initialize crew work pagination
    if (crewWorkData.length > 0) {
        displayCrewWork(1);
    }
});

// Payment and work data for JavaScript access
const paymentsData = <?= json_encode($crewPayments) ?>;
const crewWorkData = <?= json_encode($crewWork) ?>;

// Crew work pagination
let currentCrewWorkPage = 1;
const crewWorkItemsPerPage = 5;

function displayCrewWork(page = 1) {
    const sortedWork = crewWorkData.sort((a, b) => new Date(b.date) - new Date(a.date));
    const totalItems = sortedWork.length;
    const totalPages = Math.ceil(totalItems / crewWorkItemsPerPage);
    const startIndex = (page - 1) * crewWorkItemsPerPage;
    const endIndex = startIndex + crewWorkItemsPerPage;
    const pageItems = sortedWork.slice(startIndex, endIndex);
    
    const tbody = document.getElementById('crew-work-tbody');
    tbody.innerHTML = '';
    
    pageItems.forEach(work => {
        const row = document.createElement('tr');
        
        const customerInfo = work.customer_name ? 
            `<strong>${work.customer_name}</strong><br>
             ${work.customer_phone ? `<small class="text-muted">${work.customer_phone}</small>` : ''}` :
            '<span class="text-muted">-</span>';
        
        row.innerHTML = `
            <td>${new Date(work.date).toLocaleDateString('fr-FR')} ${new Date(work.date).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}</td>
            <td>${work.type}</td>
            <td>${customerInfo}</td>
            <td class="fw-bold text-success">${parseFloat(work.amount).toLocaleString('fr-FR', {minimumFractionDigits: 3})} TND</td>
            <td>${work.notes || ''}</td>
        `;
        tbody.appendChild(row);
    });
    
    // Update pagination
    if (totalPages > 1) {
        updateCrewWorkPagination(page, totalPages);
        const pagination = document.getElementById('crew-work-pagination');
        if (pagination) pagination.style.display = 'block';
    } else {
        const pagination = document.getElementById('crew-work-pagination');
        if (pagination) pagination.style.display = 'none';
    }
}

function updateCrewWorkPagination(currentPage, totalPages) {
    const pagination = document.querySelector('#crew-work-pagination .pagination');
    if (!pagination) return;
    
    pagination.innerHTML = '';
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="changeCrewWorkPage(${currentPage - 1})">Précédent</a>`;
    pagination.appendChild(prevLi);
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="changeCrewWorkPage(${i})">${i}</a>`;
        pagination.appendChild(li);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="changeCrewWorkPage(${currentPage + 1})">Suivant</a>`;
    pagination.appendChild(nextLi);
}

function changeCrewWorkPage(page) {
    const totalPages = Math.ceil(crewWorkData.length / crewWorkItemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentCrewWorkPage = page;
        displayCrewWork(page);
    }
}

function showPaymentDetails(paymentId) {
    const payment = paymentsData.find(p => p.id === paymentId);
    if (!payment) return;
    
    // Format date
    const date = new Date(payment.payment_date || payment.created_at);
    document.getElementById('detail-date').textContent = date.toLocaleDateString('fr-FR');
    
    // Format period from period_start date
    if (payment.period_start) {
        const startDate = new Date(payment.period_start);
        const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                           'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        document.getElementById('detail-period').textContent = 
            monthNames[startDate.getMonth()] + ' ' + startDate.getFullYear();
    }
    
    // Fill calculation details using correct field names
    document.getElementById('detail-base-salary').textContent = 
        (payment.base_salary || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    document.getElementById('detail-revenue').textContent = 
        (payment.work_revenue || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    document.getElementById('detail-bonus-revenue').textContent = 
        (payment.eligible_bonus || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    document.getElementById('detail-bonus-percentage').textContent = 
        (payment.bonus_percentage || 0).toFixed(1);
    document.getElementById('detail-bonus-amount').textContent = 
        (payment.bonus_amount || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    
    // Calculate subtotal (base + bonus)
    const subtotal = (payment.base_salary || 0) + (payment.bonus_amount || 0);
    document.getElementById('detail-subtotal').textContent = 
        subtotal.toLocaleString('fr-FR', {minimumFractionDigits: 3});
    
    document.getElementById('detail-advances').textContent = 
        (payment.advances_deducted || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    document.getElementById('detail-net-payment').textContent = 
        (payment.net_payment || 0).toLocaleString('fr-FR', {minimumFractionDigits: 3});
    
    // Show/hide notes
    if (payment.notes && payment.notes.trim()) {
        document.getElementById('detail-notes').textContent = payment.notes;
        document.getElementById('detail-notes-section').style.display = 'block';
    } else {
        document.getElementById('detail-notes-section').style.display = 'none';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    modal.show();
}
</script>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Date:</strong> <span id="detail-date"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Période:</strong> <span id="detail-period"></span>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Calcul du Salaire</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-8">Salaire de base:</div>
                            <div class="col-4 text-end fw-bold">
                                <span id="detail-base-salary"></span> TND
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-8">Chiffre d'affaires du mois:</div>
                            <div class="col-4 text-end">
                                <span id="detail-revenue"></span> TND
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-8">Chiffre au-dessus de 2000 TND:</div>
                            <div class="col-4 text-end">
                                <span id="detail-bonus-revenue"></span> TND
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-8">Bonus (<span id="detail-bonus-percentage"></span>%):</div>
                            <div class="col-4 text-end text-success">
                                + <span id="detail-bonus-amount"></span> TND
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-2">
                            <div class="col-8"><strong>Sous-total:</strong></div>
                            <div class="col-4 text-end fw-bold">
                                <span id="detail-subtotal"></span> TND
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-8 text-danger">Avances déduites:</div>
                            <div class="col-4 text-end text-danger">
                                - <span id="detail-advances"></span> TND
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-8"><strong>Montant Net Payé:</strong></div>
                            <div class="col-4 text-end fw-bold text-success fs-5">
                                <span id="detail-net-payment"></span> TND
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3" id="detail-notes-section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Notes</h6>
                        </div>
                        <div class="card-body">
                            <p id="detail-notes"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>