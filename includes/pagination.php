<?php

function paginatedSelect(
    string $select,
    string $fromWhere,
    array $params,
    string $orderBy,
    int $page = 1,
    int $perPage = 15
): array {
    $db = getDB();
    $page = max(1, $page);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $countStmt = $db->prepare("SELECT COUNT(*) {$fromWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare("{$select} {$fromWhere} {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return [
        'data'        => $data,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'from'        => $total ? $offset + 1 : 0,
        'to'          => min($offset + $perPage, $total),
    ];
}

function getListPage(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function getListPerPage(): int
{
    return max(5, min(100, (int) ($_GET['per_page'] ?? 15)));
}

function paginationQuery(array $filters): array
{
    return array_filter($filters, static fn($value) => $value !== '' && $value !== null);
}

function paginationUrl(int $page, array $filters): string
{
    $filters['page'] = $page;
    return '?' . http_build_query(paginationQuery($filters));
}

function renderPagination(array $pagination, array $filters = []): string
{
    if ($pagination['total'] === 0) {
        return '<div class="pagination-bar"><div class="pagination-info">No records found</div></div>';
    }

    $page = $pagination['page'];
    $totalPages = $pagination['total_pages'];
    $filters['per_page'] = $pagination['per_page'];

    $html = '<div class="pagination-bar">';
    $html .= '<div class="pagination-info">';
    $html .= 'Showing ' . number_format($pagination['from']) . '–' . number_format($pagination['to']);
    $html .= ' of ' . number_format($pagination['total']) . ' records';
    $html .= '</div>';

    if ($totalPages <= 1) {
        $html .= '</div>';
        return $html;
    }

    $html .= '<div class="pagination-links">';

    if ($page > 1) {
        $html .= '<a class="page-link" href="' . e(paginationUrl(1, $filters)) . '">First</a>';
        $html .= '<a class="page-link" href="' . e(paginationUrl($page - 1, $filters)) . '">Prev</a>';
    }

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    if ($start > 1) {
        $html .= '<span class="page-ellipsis">…</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<a class="page-link' . $active . '" href="' . e(paginationUrl($i, $filters)) . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        $html .= '<span class="page-ellipsis">…</span>';
    }

    if ($page < $totalPages) {
        $html .= '<a class="page-link" href="' . e(paginationUrl($page + 1, $filters)) . '">Next</a>';
        $html .= '<a class="page-link" href="' . e(paginationUrl($totalPages, $filters)) . '">Last</a>';
    }

    $html .= '</div></div>';

    return $html;
}

function perPageOptions(): array
{
    return [10, 15, 25, 50, 100];
}
