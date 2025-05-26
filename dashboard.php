<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$crew = loadData('crew');
$work = loadData('work');
$charges = loadData('charges');
$advances = loadData('advances');
$payments = loadData('payments');

// Calculate today's statistics
$today = date('Y-m-d');
$todayWork = array_filter($work, function($w) use ($today) {
    return substr($w['date'], 0, 10) === $today;
});

$todayRevenue = array_sum(array_column($todayWork, 'amount'));
$todayWorkCount = count($todayWork);

// Calculate this month's statistics
$thisMonth = date('Y-m');
$monthWork = array_filter($work, function($w) use ($thisMonth) {
    return substr($w['date'], 0, 7) === $thisMonth;
});

$monthRevenue = array_sum(array_column($monthWork, 'amount'));
$monthWorkCount = count($monthWork);

// Calculate total advances
$totalAdvances = array_sum(array_filter(array_column($advances, 'amount'), function($a) {
    return $a > 0;
}));

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Tableau de Bord</h1>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?= $todayWorkCount ?></h4>
                                    <p class="card-text">Travaux Aujourd'hui</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-cut fa-2x"></i>
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
                                    <h4 class="card-title"><?= number_format($todayRevenue, 3) ?> TND</h4>
                                    <p class="card-text">Revenus Aujourd'hui</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-euro-sign fa-2x"></i>
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
                                    <h4 class="card-title"><?= $monthWorkCount ?></h4>
                                    <p class="card-text">Travaux ce Mois</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar fa-2x"></i>
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
                                    <h4 class="card-title"><?= number_format($totalAdvances, 3) ?> TND</h4>
                                    <p class="card-text">Avances Totales</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Derniers Travaux</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($work)): ?>
                                <p class="text-muted">Aucun travail enregistré.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Équipe</th>
                                                <th>Montant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentWork = array_slice(array_reverse($work), 0, 5);
                                            foreach ($recentWork as $w):
                                                $crewMember = array_filter($crew, function($c) use ($w) {
                                                    return $c['id'] === $w['crew_id'];
                                                });
                                                $crewMember = reset($crewMember);
                                            ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($w['date'])) ?></td>
                                                    <td><?= htmlspecialchars($w['type']) ?></td>
                                                    <td><?= $crewMember ? htmlspecialchars($crewMember['name']) : 'N/A' ?></td>
                                                    <td><?= number_format($w['amount'], 2) ?> TND</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Équipe Active</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($crew)): ?>
                                <p class="text-muted">Aucun membre d'équipe enregistré.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($crew as $member): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($member['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($member['position']) ?></small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php
                                                $memberWork = array_filter($monthWork, function($w) use ($member) {
                                                    return $w['crew_id'] === $member['id'];
                                                });
                                                echo count($memberWork);
                                                ?> travaux
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
