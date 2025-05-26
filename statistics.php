<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check user role and set permissions
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$isCrew = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'crew';

if ($isCrew) {
    $crew_id = $_SESSION['user']['crew_id'];
}

$crew = loadData('crew');
$work = loadData('work');
$payments = loadData('payments');
$charges = loadData('charges');

// Filter data for crew members
if ($isCrew) {
    $work = array_filter($work, function($w) use ($crew_id) {
        return $w['crew_id'] === $crew_id;
    });
    $payments = array_filter($payments, function($p) use ($crew_id) {
        return $p['crew_id'] === $crew_id;
    });
    $crew = array_filter($crew, function($c) use ($crew_id) {
        return $c['id'] === $crew_id;
    });
}

// Get date filters
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? '';
$crew_filter = $_GET['crew_filter'] ?? '';

// For crew members, force filter to their own ID
if ($isCrew) {
    $crew_filter = $crew_id;
}

// Generate year options (current year and 2 years back)
$currentYear = date('Y');
$yearOptions = [];
for ($i = 0; $i < 3; $i++) {
    $yearOptions[] = $currentYear - $i;
}

// Calculate monthly statistics
$monthlyStats = [];
$crewMonthlyStats = [];

// Initialize crew stats
foreach ($crew as $member) {
    $crewMonthlyStats[$member['id']] = [
        'name' => $member['name'],
        'months' => []
    ];
}

// Process work data by month
for ($m = 1; $m <= 12; $m++) {
    $monthKey = sprintf('%04d-%02d', $year, $m);
    $monthName = date('F', mktime(0, 0, 0, $m, 1));
    
    // Filter work for this month
    $monthWork = array_filter($work, function($w) use ($year, $m) {
        $workDate = substr($w['date'], 0, 7);
        return $workDate === sprintf('%04d-%02d', $year, $m);
    });
    
    // Calculate total revenue for the month
    $monthRevenue = array_sum(array_column($monthWork, 'amount'));
    $monthWorkCount = count($monthWork);
    
    $monthlyStats[$monthKey] = [
        'month' => $monthName,
        'revenue' => $monthRevenue,
        'work_count' => $monthWorkCount,
        'crew_stats' => []
    ];
    
    // Calculate per-crew statistics
    foreach ($crew as $member) {
        $crewWork = array_filter($monthWork, function($w) use ($member) {
            return $w['crew_id'] === $member['id'];
        });
        
        $crewRevenue = array_sum(array_column($crewWork, 'amount'));
        $crewWorkCount = count($crewWork);
        
        $monthlyStats[$monthKey]['crew_stats'][$member['id']] = [
            'name' => $member['name'],
            'revenue' => $crewRevenue,
            'work_count' => $crewWorkCount
        ];
        
        $crewMonthlyStats[$member['id']]['months'][$monthKey] = [
            'month' => $monthName,
            'revenue' => $crewRevenue,
            'work_count' => $crewWorkCount
        ];
    }
}

// Filter by specific month if selected
if ($month) {
    $monthKey = sprintf('%04d-%02d', $year, $month);
    $monthlyStats = isset($monthlyStats[$monthKey]) ? [$monthKey => $monthlyStats[$monthKey]] : [];
}

// Calculate yearly totals
$yearlyRevenue = 0;
$yearlyWorkCount = 0;
foreach ($monthlyStats as $stats) {
    $yearlyRevenue += $stats['revenue'];
    $yearlyWorkCount += $stats['work_count'];
}

// Calculate charges for the period
$yearCharges = array_filter($charges, function($c) use ($year, $month) {
    $chargeYear = substr($c['date'], 0, 4);
    if ($month) {
        $chargeMonth = substr($c['date'], 0, 7);
        return $chargeMonth === sprintf('%04d-%02d', $year, $month);
    }
    return $chargeYear === $year;
});

$totalCharges = array_sum(array_column($yearCharges, 'amount'));
$netProfit = $yearlyRevenue - $totalCharges;

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Statistiques et Rapports</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="exportStats()">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="year" class="form-label">Année</label>
                            <select class="form-control" id="year" name="year">
                                <?php foreach ($yearOptions as $y): ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="month" class="form-label">Mois (optionnel)</label>
                            <select class="form-control" id="month" name="month">
                                <option value="">Toute l'année</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= sprintf('%02d', $m) ?>" <?= $month == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <?php if ($isAdmin): ?>
                        <div class="col-md-3">
                            <label for="crew_filter" class="form-label">Équipe</label>
                            <select class="form-control" id="crew_filter" name="crew_filter">
                                <option value="">Toutes les équipes</option>
                                <?php foreach ($crew as $member): ?>
                                    <option value="<?= $member['id'] ?>" <?= $crew_filter === $member['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($member['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                <a href="statistics.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?= number_format($yearlyRevenue, 2) ?> TND</h4>
                                    <p class="card-text">Chiffre d'Affaires</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?= number_format($totalCharges, 2) ?> TND</h4>
                                    <p class="card-text">Total Charges</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-file-invoice-dollar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-<?= $netProfit >= 0 ? 'success' : 'warning' ?> text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?= number_format($netProfit, 2) ?> TND</h4>
                                    <p class="card-text">Bénéfice Net</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-balance-scale fa-2x"></i>
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
                                    <h4 class="card-title"><?= $yearlyWorkCount ?></h4>
                                    <p class="card-text">Total Travaux</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-cut fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Statistics -->
            <?php if (!$crew_filter): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Statistiques Mensuelles - <?= $year ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mois</th>
                                        <th>Revenus</th>
                                        <th>Nombre de Travaux</th>
                                        <th>Moyenne par Travail</th>
                                        <?php foreach ($crew as $member): ?>
                                            <th><?= htmlspecialchars($member['name']) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyStats as $monthKey => $stats): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stats['month']) ?></td>
                                            <td><?= number_format($stats['revenue'], 2) ?> TND</td>
                                            <td><?= $stats['work_count'] ?></td>
                                            <td>
                                                <?= $stats['work_count'] > 0 ? number_format($stats['revenue'] / $stats['work_count'], 2) : '0.00' ?> TND
                                            </td>
                                            <?php foreach ($crew as $member): ?>
                                                <td>
                                                    <?php
                                                    $crewStats = $stats['crew_stats'][$member['id']] ?? ['revenue' => 0, 'work_count' => 0];
                                                    echo number_format($crewStats['revenue'], 2) . ' TND (' . $crewStats['work_count'] . ')';
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Crew Performance -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Performance par Équipe</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $crewToShow = $crew_filter ? array_filter($crew, function($c) use ($crew_filter) {
                            return $c['id'] === $crew_filter;
                        }) : $crew;
                        
                        foreach ($crewToShow as $member): 
                            $memberStats = $crewMonthlyStats[$member['id']] ?? ['months' => []];
                            $totalRevenue = array_sum(array_column($memberStats['months'], 'revenue'));
                            $totalWork = array_sum(array_column($memberStats['months'], 'work_count'));
                            $avgPerWork = $totalWork > 0 ? $totalRevenue / $totalWork : 0;
                        ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?= htmlspecialchars($member['name']) ?></h6>
                                        <p class="card-text">
                                            <strong>Total Revenus:</strong> <?= number_format($totalRevenue, 2) ?> TND<br>
                                            <strong>Total Travaux:</strong> <?= $totalWork ?><br>
                                            <strong>Moyenne/Travail:</strong> <?= number_format($avgPerWork, 2) ?> TND
                                        </p>
                                        
                                        <!-- Mini chart showing monthly progression -->
                                        <div class="mt-3">
                                            <small class="text-muted">Évolution mensuelle:</small>
                                            <div class="d-flex align-items-end" style="height: 60px; gap: 2px;">
                                                <?php 
                                                $maxRevenue = max(array_column($memberStats['months'], 'revenue')) ?: 1;
                                                foreach ($memberStats['months'] as $monthData): 
                                                    $height = $maxRevenue > 0 ? ($monthData['revenue'] / $maxRevenue) * 50 : 0;
                                                ?>
                                                    <div class="bg-primary" style="width: 8px; height: <?= $height ?>px; min-height: 2px;" 
                                                         title="<?= $monthData['month'] ?>: <?= number_format($monthData['revenue'], 2) ?> TND"></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportStats() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Statistiques Salon de Coiffure - <?= $year ?>\n\n";
    
    // Add summary
    csvContent += "Résumé,Montant\n";
    csvContent += "Chiffre d'Affaires,<?= $yearlyRevenue ?>\n";
    csvContent += "Total Charges,<?= $totalCharges ?>\n";
    csvContent += "Bénéfice Net,<?= $netProfit ?>\n";
    csvContent += "Total Travaux,<?= $yearlyWorkCount ?>\n\n";
    
    // Add monthly data
    csvContent += "Mois,Revenus,Travaux\n";
    <?php foreach ($monthlyStats as $stats): ?>
        csvContent += "<?= $stats['month'] ?>,<?= $stats['revenue'] ?>,<?= $stats['work_count'] ?>\n";
    <?php endforeach; ?>
    
    // Download file
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "statistiques_salon_<?= $year ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include 'includes/footer.php'; ?>
