<?php

namespace GlpiPlugin\Gestaoeventos;

use Session;

/**
 * Padrão de lista dos plugins: a "Tabela principal" (estilo monday), igual à do Gestão de Demandas e do
 * Customer 360 — grupos recolhíveis, linha com borda colorida, avatar, célula de status colorida, linha do
 * tempo, prazo e estrelas. Os auxiliares daqui servem a qualquer tela do plugin; `eventos()` é a lista de eventos.
 */
class Lista
{
    private const MONTHS = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    private const MONTHS_LONG = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    private const PER_PAGE = 50;

    // ------------------------------------------------------------------ auxiliares da tabela

    public static function avatar(string $name, string $title = ''): string
    {
        $name = trim($name);
        if ($name === '' || $name === '—') {
            return "<span class='gl-av none' title='Sem responsável'>?</span>";
        }
        $parts = preg_split('/\s+/', $name);
        $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 1, 1)));
        return "<span class='gl-av' style='--h:" . (crc32($name) % 360) . "' title='" . Menu::e($title !== '' ? $title : $name) . "'>" . Menu::e($ini) . '</span>';
    }

    /** Célula de status: preenche a célula inteira com a cor. */
    public static function stCell(string $label, string $color): string
    {
        return "<div class='gl-st' style='--c:$color'>" . Menu::e($label) . '</div>';
    }

    public static function timeline(float $pct, string $title = ''): string
    {
        return "<div class='gl-tl' title='" . Menu::e($title) . "'><span style='width:" . round(max(0, min(100, $pct)), 1) . "%'></span></div>";
    }

    public static function stars(int $n, string $title = ''): string
    {
        $n = max(0, min(5, $n));
        return "<span class='gl-cell-pr' title='" . Menu::e($title) . "'>" . str_repeat("<span class='on'>★</span>", $n) . str_repeat('<span>★</span>', 5 - $n) . '</span>';
    }

    /** "9 jun" (com o ano quando não é o corrente). */
    public static function shortDate(?string $d): string
    {
        if (!$d || str_starts_with($d, '0000')) {
            return '—';
        }
        $t = strtotime($d);
        return (int) date('j', $t) . ' ' . self::MONTHS[(int) date('n', $t)] . (date('Y', $t) !== date('Y') ? ' ' . date('Y', $t) : '');
    }

    /** Grupo por mês (Meses anteriores, Este mês, Próximo mês, meses seguintes, Sem data): [chave de ordem, título, cor]. */
    public static function monthGroup(?string $date): array
    {
        $palette = ['#14b8a6', '#f97316', '#ec4899', '#84cc16', '#0ea5e9', '#eab308'];
        $now = date('Y-m');
        $next = date('Y-m', strtotime('first day of next month'));
        if (!$date || str_starts_with($date, '0000')) {
            return ['zz', 'Sem data', '#94a3b8'];
        }
        $m = substr($date, 0, 7);
        if ($m < $now) {
            return ['0', 'Meses anteriores', '#ef4444'];
        }
        if ($m === $now) {
            return ['1-' . $m, 'Este mês', '#3b82f6'];
        }
        if ($m === $next) {
            return ['1-' . $m, 'Próximo mês', '#a855f7'];
        }
        return ['1-' . $m, ucfirst(self::MONTHS_LONG[(int) substr($m, 5, 2)]) . ' de ' . substr($m, 0, 4), $palette[((int) substr($m, 5, 2)) % count($palette)]];
    }

    /** Campo de filtro: rótulo pequeno acima do controle. */
    public static function filter(string $label, string $control, string $style = ''): string
    {
        return "<div" . ($style !== '' ? " style='$style'" : '') . "><label class='lbl'>" . Menu::e($label) . "</label>$control</div>";
    }

    public static function pager(int $total, int $page, array $params): string
    {
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($pages <= 1) {
            return '';
        }
        $qs = http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
        $h = "<div class='d-flex gap-2 flex-wrap mt-3'>";
        for ($i = 1; $i <= $pages; $i++) {
            $h .= "<a class='btn btn-sm " . ($i === $page ? 'btn-primary' : 'btn-outline-secondary') . "' href='?page=$i" . ($qs ? '&' . Menu::e($qs) : '') . "'>$i</a>";
        }
        return $h . '</div>';
    }

    /**
     * @param array $cols   colunas depois do nome: [[rótulo, largura css], ...]
     * @param array $groups [['title', 'color', 'rows' => [['name', 'sub', 'url', 'cells' => [[html, classe], ...], 'late' => bool, 'drawer' => chave]]]]
     */
    public static function mainTable(array $cols, array $groups, string $empty = 'Nada a mostrar.'): string
    {
        $tpl = 'minmax(240px, 1fr) ' . implode(' ', array_column($cols, 1));
        $h = "<div class='gl-table-main' style='--gl-cols:$tpl'>";
        if (!$groups) {
            return $h . "<div class='card card-body text-center text-secondary'>" . Menu::e($empty) . '</div></div>';
        }
        foreach ($groups as $g) {
            $h .= "<section class='gl-grp' style='--g:" . Menu::e($g['color']) . "'><div class='gl-grp-head'><button type='button' class='gl-grp-title' data-toggle aria-expanded='true'><i class='ti ti-chevron-down'></i><span>"
                . Menu::e($g['title']) . '</span><em>' . count($g['rows']) . "</em></button><div class='gl-col-names'>";
            foreach ($cols as [$label]) {
                $h .= '<span>' . Menu::e($label) . '</span>';
            }
            $h .= "</div></div><div class='gl-grp-body'>";
            foreach ($g['rows'] as $r) {
                $h .= "<div class='gl-row" . (!empty($r['late']) ? ' late' : '') . "'" . (!empty($r['drawer']) ? " tabindex='0' role='button' data-drawer='" . Menu::e($r['drawer']) . "'" : '') . ">"
                    . "<div class='gl-cell-name'><a href='" . Menu::e($r['url']) . "'>" . Menu::e($r['name']) . '</a>' . (!empty($r['sub']) ? '<small>' . Menu::e($r['sub']) . '</small>' : '') . '</div>';
                foreach ($r['cells'] as [$cell, $cls]) {
                    $h .= "<div class='$cls'>$cell</div>";
                }
                $h .= '</div>';
            }
            $h .= '</div></section>';
        }
        return $h . '</div>';
    }

    /** Agrupa linhas já montadas ([chave, título, cor] => linhas) e ordena os grupos pela chave. */
    public static function groups(array $buckets): array
    {
        ksort($buckets);
        return array_values($buckets);
    }

    // ------------------------------------------------------------------ lista de eventos

    public static function eventos(): void
    {
        global $DB;
        $e = [Menu::class, 'e'];
        $get = fn (string $k) => trim((string) ($_GET[$k] ?? ''));
        $q = $get('q');
        $fStatus = $get('status');
        $fType = $get('type');
        $fPhase = $get('phase');
        $fAppr = $get('approval');
        $fGp = (int) $get('gp');
        $group = in_array($get('group'), ['mes', 'status', 'etapa'], true) ? $get('group') : 'mes';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $where = ['is_deleted' => 0] + Acesso::scope() + getEntitiesRestrictCriteria(Evento::getTable(), 'entities_id', '', true);
        $base = iterator_to_array($DB->request(['FROM' => Evento::getTable(), 'WHERE' => $where, 'ORDER' => 'date_start']), false);

        // fases de todos os eventos de uma vez
        $phases = [];
        if ($base) {
            foreach ($DB->request(['FROM' => Fase::getTable(), 'WHERE' => ['plugin_gestaoeventos_eventos_id' => array_map(fn ($r) => (int) $r['id'], $base)], 'ORDER' => 'position']) as $p) {
                $phases[(int) $p['plugin_gestaoeventos_eventos_id']][$p['code']] = $p;
            }
        }
        $info = function (array $c) use ($phases): array {
            $ph = $phases[(int) $c['id']] ?? [];
            $active = null;
            $done = 0;
            foreach ($ph as $p) {
                $done += in_array($p['status'], ['done', 'skipped'], true) ? 1 : 0;
                if ($p['status'] === 'active') {
                    $active = $p;
                }
            }
            $late = $active && Fase::isLate($active);
            $warn = $active && !$late && Fase::isWarn($active);
            $today = date('Y-m-d');
            $over = (int) $c['status'] === Evento::OPEN && $c['date_finish'] && $c['date_finish'] < $today && !$c['is_draft'];
            return ['active' => $active, 'done' => $done, 'total' => count($ph), 'late' => $late, 'warn' => $warn, 'over' => $over];
        };

        // filtros
        $rows = array_values(array_filter($base, function (array $c) use ($q, $fStatus, $fType, $fPhase, $fAppr, $fGp) {
            return ($q === '' || stripos($c['name'] . ' ' . $c['code'] . ' ' . $c['city'] . ' ' . $c['partner'] . ' ' . $c['venue'], $q) !== false)
                && ($fStatus === '' || (string) $c['status'] === $fStatus) && ($fType === '' || $c['event_type'] === $fType)
                && ($fPhase === '' || $c['current_phase'] === $fPhase) && ($fAppr === '' || $c['approval_status'] === $fAppr) && (!$fGp || (int) $c['users_id_gp'] === $fGp);
        }));

        // indicadores (abrem a gaveta com a lista)
        $infos = [];
        foreach ($base as $c) {
            $infos[(int) $c['id']] = $info($c);
        }
        $today = date('Y-m-d');
        $in30 = date('Y-m-d', strtotime('+30 days'));
        $kp = [
            'andamento' => ['Em andamento', 'ti-progress', '#3b82f6', array_filter($base, fn ($c) => (int) $c['status'] === Evento::OPEN && !$c['is_draft'])],
            'atraso' => ['Com etapa atrasada', 'ti-alarm', '#ef4444', array_filter($base, fn ($c) => $infos[(int) $c['id']]['late'])],
            'aprovacao' => ['Aguardando aprovação', 'ti-signature', '#f59e0b', array_filter($base, fn ($c) => $c['approval_status'] === 'em_aprovacao')],
            'proximos' => ['Próximos 30 dias', 'ti-calendar-event', '#06b6d4', array_filter($base, fn ($c) => (int) $c['status'] === Evento::OPEN && $c['date_start'] >= $today && $c['date_start'] <= $in30)],
        ];

        echo "<div class='ge ge-lista'>" . Menu::title('Eventos', 'evento', Menu::button('Meu Painel', Menu::url('/front/aprovacoes.php'), 'ti-user-check', 'btn-outline-secondary')
            . Menu::button('Calendário', Menu::url('/front/calendario.php'), 'ti-calendar', 'btn-outline-secondary') . Menu::button('Painel', Menu::url('/front/painel.php'), 'ti-chart-dots-3', 'btn-outline-secondary')
            . Menu::button('Busca avançada', Menu::url('/front/evento.php') . '?native=1', 'ti-adjustments-search', 'btn-outline-secondary') . (Evento::canCreate() ? Menu::button('Novo evento', Evento::getFormURL()) : ''));

        echo "<div class='row g-3 mb-3'>";
        foreach ($kp as $key => [$label, $icon, $color, $list]) {
            echo "<div class='col-6 col-md-3'><button type='button' class='ge-kpi ge-kpi-btn' style='--c:$color' data-drawer='k-$key'><div class='ge-kpi-top'><span class='ge-kpi-label'>$label</span><i class='ti $icon'></i></div>"
                . "<div class='ge-kpi-value'>" . count($list) . "</div><div class='ge-kpi-sub'>de " . count($base) . " eventos</div><div class='ge-kpi-more'>ver lista <i class='ti ti-chevron-right'></i></div></button></div>";
        }
        echo '</div>';
        foreach ($kp as $key => [$label, $icon, $color, $list]) {
            $h = "<div class='ge-dr-list'>";
            foreach ($list as $c) {
                $h .= "<div class='ge-dr-item'><div class='ge-dr-row'><a href='" . $e(Evento::getFormURLWithID((int) $c['id'])) . "'><b>" . $e($c['code']) . "</b></a><span class='ge-dr-name'>" . $e($c['name']) . "</span><span class='ge-dr-val small'>" . $e(self::shortDate($c['date_start'])) . "</span></div>"
                    . "<div class='ge-dr-sub'>" . $e($c['city']) . ' · ' . $e($c['current_phase'] ? Fase::name((string) $c['current_phase']) : 'sem etapa') . "</div></div>";
            }
            $h .= ($list ? '' : "<p class='text-secondary'>Nenhum evento.</p>") . '</div>';
            echo "<template id='dr-k-$key' data-title='" . $e($label) . "' data-icon='$icon' data-color='$color'>$h</template>";
        }

        // filtros
        $gps = [];
        foreach ($base as $c) {
            if ((int) $c['users_id_gp']) {
                $gps[(int) $c['users_id_gp']] = getUserName((int) $c['users_id_gp']);
            }
        }
        asort($gps);
        $sel = fn (string $name, array $opts, string $val) => "<select class='form-select' name='$name'>" . Menu::options($opts, $val) . '</select>';
        $etapas = [];
        foreach (Etapa::all() as $code => $et) {
            $etapas[$code] = Fase::name($code);
        }
        echo "<form method='get' class='card card-body mb-3'><div class='gl-filters'>"
            . self::filter('Buscar', "<input class='form-control' name='q' value='" . $e($q) . "' placeholder='nome, código, cidade, parceiro…'>", 'min-width:220px;flex:1')
            . self::filter('Situação', $sel('status', ['' => 'Todas'] + Menu::labels(Evento::STATUSES), $fStatus))
            . self::filter('Tipo', $sel('type', ['' => 'Todos'] + Menu::labels(Evento::TYPES), $fType))
            . self::filter('Etapa', $sel('phase', ['' => 'Todas'] + $etapas, $fPhase))
            . self::filter('Aprovação', $sel('approval', ['' => 'Todas'] + Menu::labels(Evento::APPROVAL), $fAppr))
            . self::filter('GP', $sel('gp', ['' => 'Todos'] + array_map(fn ($n) => $n, $gps), (string) $fGp))
            . self::filter('Agrupar por', $sel('group', ['mes' => 'Mês do evento', 'status' => 'Situação', 'etapa' => 'Etapa atual'], $group))
            . "<div><button class='btn btn-primary'><i class='ti ti-search me-1'></i>Filtrar</button></div></div></form>";

        // linhas
        $total = count($rows);
        $rows = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $money = Acesso::canView('financeiro');
        $buckets = [];
        $drawers = '';
        $prio = ['baixa' => 1, 'media' => 3, 'alta' => 5];
        foreach ($rows as $c) {
            $id = (int) $c['id'];
            $i = $infos[$id];
            $open = (int) $c['status'] === Evento::OPEN;
            if (!$open) {
                [$stLabel, $stColor] = [Evento::STATUSES[(int) $c['status']][0], Evento::STATUSES[(int) $c['status']][1]];
            } elseif ($c['is_draft']) {
                [$stLabel, $stColor] = ['Rascunho', '#94a3b8'];
            } else {
                $stLabel = $c['current_phase'] ? Fase::name((string) $c['current_phase']) : 'Em andamento';
                $stColor = $i['late'] ? '#ef4444' : ($i['warn'] ? '#f59e0b' : '#3b82f6');
            }
            $gp = (int) $c['users_id_gp'] ? getUserName((int) $c['users_id_gp']) : '';
            $own = (int) $c['users_id_owner'] ? getUserName((int) $c['users_id_owner']) : '';
            $req = (int) $c['users_id_requester'] ? getUserName((int) $c['users_id_requester']) : '';
            $respTitle = 'GP: ' . ($gp ?: '—') . ' · Dono: ' . ($own ?: '—') . ' · Solicitante: ' . ($req ?: '—');
            $pct = !$open ? ((int) $c['status'] === Evento::DONE ? 100 : 0) : ($i['total'] ? 100 * $i['done'] / $i['total'] : 0);
            $ap = Evento::APPROVAL[$c['approval_status']] ?? ['—', '#94a3b8'];
            $pj = Evento::PROJECT_STATUS[$c['project_status']] ?? ['—', '#94a3b8'];
            $dates = self::shortDate($c['date_start']) . ($c['date_finish'] && $c['date_finish'] !== $c['date_start'] ? ' – ' . self::shortDate($c['date_finish']) : '');
            $cells = [
                [self::avatar($gp ?: ($own ?: $req), $respTitle), 'gl-cell-av'],
                [self::stCell($stLabel, $stColor), 'gl-cell-st'],
                [self::stCell($ap[0], $ap[1]), 'gl-cell-st'],
                [self::stCell($pj[0], $pj[1]), 'gl-cell-st'],
                [self::timeline($pct, $i['done'] . ' de ' . $i['total'] . ' etapas concluídas'), 'gl-cell-tl'],
            ];
            if ($money) {
                $cells[] = [(float) $c['budget_estimated'] ? $e(Menu::money((float) $c['budget_estimated'])) : '—', 'gl-cell-val'];
            }
            $cells[] = [$e($dates), 'gl-cell-dl' . ($i['over'] ? ' late' : '')];
            $cells[] = [self::stars($prio[$c['commercial_priority']] ?? 0, 'Prioridade comercial: ' . (Evento::PRIORITIES[$c['commercial_priority']] ?? 'não definida')), 'gl-cell-pr-wrap'];
            $row = ['name' => $c['name'], 'sub' => $c['code'] . ' · ' . (Evento::TYPES[$c['event_type']][0] ?? '') . ($c['city'] ? ' · ' . $c['city'] : ''), 'url' => Evento::getFormURLWithID($id),
                'cells' => $cells, 'late' => $i['late'] || $i['over'], 'drawer' => 'ev' . $id];
            if ($group === 'status') {
                $key = $open ? ($c['is_draft'] ? '1' : '0') : (string) (2 + (int) $c['status']);
                $g = [$key, $open ? ($c['is_draft'] ? 'Rascunhos' : 'Em andamento') : Evento::STATUSES[(int) $c['status']][0], $open ? ($c['is_draft'] ? '#94a3b8' : '#3b82f6') : Evento::STATUSES[(int) $c['status']][1]];
            } elseif ($group === 'etapa') {
                $pos = $c['current_phase'] && isset($phases[$id][$c['current_phase']]) ? (int) $phases[$id][$c['current_phase']]['position'] : 999;
                $g = [sprintf('%03d', $pos), $open && $c['current_phase'] ? Fase::name((string) $c['current_phase']) : ($open ? 'Rascunhos' : Evento::STATUSES[(int) $c['status']][0]), $stColor];
            } else {
                $g = self::monthGroup($c['date_start']);
            }
            $buckets[$g[0]] ??= ['title' => $g[1], 'color' => $g[2], 'rows' => []];
            $buckets[$g[0]]['rows'][] = $row;

            // gaveta do evento
            $ev = new Evento();
            $ev->getFromDB($id);
            $h = "<div class='ge-dr-lead'>" . Evento::statusBadge((int) $c['status']) . ' ' . Evento::badge(Evento::APPROVAL, (string) $c['approval_status']) . ' ' . Evento::badge(Evento::PROJECT_STATUS, (string) $c['project_status']) . "</div>"
                . "<table class='table table-sm'><tbody>"
                . "<tr><th>Código</th><td>" . $e($c['code']) . "</td></tr><tr><th>Tipo</th><td>" . $e(Evento::TYPES[$c['event_type']][0] ?? '') . "</td></tr>"
                . "<tr><th>Data</th><td>" . $e(Menu::date($c['date_start']) . ($c['date_finish'] && $c['date_finish'] !== $c['date_start'] ? ' a ' . Menu::date($c['date_finish']) : '')) . "</td></tr>"
                . "<tr><th>Local</th><td>" . $e(trim($c['city'] . ' · ' . $c['venue'], ' ·')) . "</td></tr>"
                . "<tr><th>GP</th><td>" . $e($gp ?: '—') . "</td></tr><tr><th>Dono</th><td>" . $e($own ?: '—') . "</td></tr><tr><th>Solicitante</th><td>" . $e($req ?: '—') . "</td></tr>"
                . ($money && (float) $c['budget_estimated'] ? "<tr><th>Orçamento previsto</th><td>" . $e(Menu::money((float) $c['budget_estimated'])) . "</td></tr>" : '')
                . ($c['partner'] ? "<tr><th>Parceiro</th><td>" . $e($c['partner']) . "</td></tr>" : '') . "</tbody></table>";
            if ($open && $i['active']) {
                $h .= "<h4 class='mt-3'>Etapa atual</h4>" . Fase::timer($i['active'], 'panel') . Fase::summary($ev, $i['active'], $phases[$id]);
            }
            $h .= "<div class='d-flex gap-2 mt-3 flex-wrap'><a class='btn btn-primary' href='" . $e(Evento::getFormURLWithID($id)) . "'><i class='ti ti-external-link me-1'></i>Abrir evento</a>"
                . "<a class='btn btn-outline-secondary' href='" . $e(Evento::tabUrl($id, 'aprovacoes')) . "'><i class='ti ti-signature me-1'></i>Aprovações</a></div>";
            $drawers .= "<template id='dr-ev$id' data-title='" . $e($c['name']) . "' data-icon='ti-calendar-event' data-color='$stColor'>$h</template>";
        }
        $cols = [['Resp.', '70px'], ['Etapa atual', '170px'], ['Aprovação', '130px'], ['Projeto', '130px'], ['Andamento', '160px']];
        if ($money) {
            $cols[] = ['Orçamento', '120px'];
        }
        $cols[] = ['Data', '120px'];
        $cols[] = ['Prioridade', '110px'];
        echo "<div class='mb-1 text-secondary small'>$total evento(s)" . ($q !== '' || $fStatus !== '' || $fType !== '' || $fPhase !== '' || $fAppr !== '' || $fGp ? ' · filtrado' : '') . '</div>';
        echo self::mainTable($cols, self::groups($buckets), 'Nenhum evento encontrado.');
        echo self::pager($total, $page, ['q' => $q, 'status' => $fStatus, 'type' => $fType, 'phase' => $fPhase, 'approval' => $fAppr, 'gp' => $fGp ?: '', 'group' => $group]);
        echo $drawers . '</div>' . Menu::script('drawer.js') . Menu::script('vivo.js');
    }
}
