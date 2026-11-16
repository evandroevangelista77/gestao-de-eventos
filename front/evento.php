<?php

use GlpiPlugin\Gestaoeventos\Evento;
use GlpiPlugin\Gestaoeventos\Menu;

Session::checkRight(Evento::$rightname, READ);

Menu::header('Eventos', 'evento');
if (!empty($_GET['native']) && !GlpiPlugin\Gestaoeventos\Acesso::restricted()) {
    // busca avançada nativa do GLPI (colunas, critérios e exportação)
    echo "<div class='ge'>" . Menu::title('Eventos', 'evento', Menu::button('Voltar à lista', Menu::url('/front/evento.php'), 'ti-list', 'btn-outline-secondary')) . "</div>";
    Search::show(Evento::class);
} else {
    GlpiPlugin\Gestaoeventos\Lista::eventos();
}
Html::footer();
