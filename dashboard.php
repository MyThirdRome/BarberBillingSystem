<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$crew = loadData('crew');
$work = loadData('work');
$charges = loadData('charges');
$advances = loadData('advances');
$payments = loadData('payments');

// Get selected month from query parameter, default to current month
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Calculate today's statistics
$today = date('Y-m-d');
$todayWork = array_filter($work, function($w) use ($today) {
    return substr($w['date'], 0, 10) === $today;
});

$todayRevenue = array_sum(array_column($todayWork, 'amount'));
$todayWorkCount = count($todayWork);

// Calculate selected month's statistics
$monthWork = array_filter($work, function($w) use ($selectedMonth) {
    return substr($w['date'], 0, 7) === $selectedMonth;
});

$monthRevenue = array_sum(array_column($monthWork, 'amount'));
$monthWorkCount = count($monthWork);

// Calculate month's charges (spendings)
$monthCharges = array_filter($charges, function($c) use ($selectedMonth) {
    return substr($c['date'], 0, 7) === $selectedMonth;
});
$monthSpendings = array_sum(array_column($monthCharges, 'amount'));

// Calculate unpaid advances (pending advances)
$pendingAdvances = array_filter($advances, function($a) {
    return $a['status'] === 'pending';
});
$totalPendingAdvances = array_sum(array_column($pendingAdvances, 'amount'));

// Calculate net result for the month (revenue - spendings)
$monthNetResult = $monthRevenue - $monthSpendings;

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Tableau de Bord</h1>
            
            <!-- Month Selector -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="card-title mb-0">Statistiques Mensuelles</h5>
                                </div>
                                <div class="col-md-6">
                                    <form method="GET" class="d-flex">
                                        <select name="month" class="form-select me-2" onchange="this.form.submit()">
                                            <?php
                                            // Generate last 12 months
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

            <!-- Monthly Statistics Cards -->
            <div class="row mb-4 g-3">
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?= number_format($monthRevenue, 2) ?></h5>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1 small">Revenus</p>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?= number_format($monthSpendings, 2) ?></h5>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1 small">Charges</p>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-credit-card fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?= number_format($totalPendingAdvances, 2) ?></h5>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1 small">Avances Impayées</p>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-hand-holding-usd fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?= $monthWorkCount ?></h5>
                                <small class="text-white-50">Nombre</small>
                                <p class="card-text mb-0 mt-1 small">Travaux</p>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-cut fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-lg-8 col-md-12">
                    <div class="card <?= $monthNetResult >= 0 ? 'bg-success' : 'bg-danger' ?> text-white h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h4 class="card-title mb-1"><?= number_format($monthNetResult, 2) ?></h4>
                                <small class="text-white-50">TND</small>
                                <p class="card-text mb-0 mt-1">Résultat Net</p>
                                <small class="text-white-50">(Revenus - Charges)</small>
                            </div>
                            <div class="ms-3">
                                <i class="fas fa-chart-line fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Quick Stats -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Statistiques d'Aujourd'hui</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h4 class="card-title"><?= $todayWorkCount ?></h4>
                                                    <p class="card-text">Travaux Effectués</p>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-cut fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h4 class="card-title"><?= number_format($todayRevenue, 2) ?> TND</h4>
                                                    <p class="card-text">Revenus du Jour</p>
                                                </div>
                                                <div class="align-self-center">
                                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                        <tbody id="recent-work-tbody">
                                            <!-- Recent work will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination for Recent Work -->
                                <nav aria-label="Recent work pagination" id="recent-work-pagination" style="display: none;">
                                    <ul class="pagination pagination-sm justify-content-center mb-0">
                                        <!-- Pagination buttons will be generated by JavaScript -->
                                    </ul>
                                </nav>
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
                                                ?> prestations
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

<script>
// Work and crew data for pagination
const workData = <?= json_encode($work) ?>;
const crewData = <?= json_encode($crew) ?>;

// Recent work pagination
let currentRecentPage = 1;
const recentItemsPerPage = 5;

function displayRecentWork(page = 1) {
    const sortedWork = workData.sort((a, b) => new Date(b.date) - new Date(a.date));
    const totalItems = sortedWork.length;
    const totalPages = Math.ceil(totalItems / recentItemsPerPage);
    const startIndex = (page - 1) * recentItemsPerPage;
    const endIndex = startIndex + recentItemsPerPage;
    const pageItems = sortedWork.slice(startIndex, endIndex);
    
    const tbody = document.getElementById('recent-work-tbody');
    tbody.innerHTML = '';
    
    pageItems.forEach(work => {
        const crewMember = crewData.find(c => c.id === work.crew_id);
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${new Date(work.date).toLocaleDateString('fr-FR')}</td>
            <td>${work.type}</td>
            <td>${crewMember ? crewMember.name : 'N/A'}</td>
            <td>${parseFloat(work.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} TND</td>
        `;
        tbody.appendChild(row);
    });
    
    // Update pagination
    if (totalPages > 1) {
        updateRecentPagination(page, totalPages);
        document.getElementById('recent-work-pagination').style.display = 'block';
    } else {
        document.getElementById('recent-work-pagination').style.display = 'none';
    }
}

function updateRecentPagination(currentPage, totalPages) {
    const pagination = document.querySelector('#recent-work-pagination .pagination');
    pagination.innerHTML = '';
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="changeRecentPage(${currentPage - 1})">Précédent</a>`;
    pagination.appendChild(prevLi);
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="changeRecentPage(${i})">${i}</a>`;
        pagination.appendChild(li);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="changeRecentPage(${currentPage + 1})">Suivant</a>`;
    pagination.appendChild(nextLi);
}

function changeRecentPage(page) {
    const totalPages = Math.ceil(workData.length / recentItemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentRecentPage = page;
        displayRecentWork(page);
    }
}

// Initialize recent work display
document.addEventListener('DOMContentLoaded', function() {
    if (workData.length > 0) {
        displayRecentWork(1);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
