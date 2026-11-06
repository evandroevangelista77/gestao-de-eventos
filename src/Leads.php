<?php

namespace GlpiPlugin\Gestaoeventos;

use Plugin;
use Throwable;

/**
 * Leads captados no evento (aba Marketing) e o envio deles para o plugin CRM, onde a equipe comercial
 * os acompanha em "Gestão de Leads". O envio não duplica: leads já enviados ficam marcados.
 */
class Leads
{
    public const SENT = 'enviado_crm';

    /** O plugin CRM (com a Gestão de Leads) está ativo? */
    public static function crmAvailable(): bool
    {
        return Plugin::isPluginActive('crm') && class_exists(\GlpiPlugin\Crm\Lead::class);
    }

    public static function pending(int $eventoId): array
    {
        return array_values(array_filter(Registro::forEvento($eventoId, 'lead_contato'), fn ($r) => $r['status'] !== self::SENT));
    }

    /** Envia ao CRM os leads do evento que ainda não foram. Devolve a mensagem para o usuário. */
    public static function sendToCrm(Evento $ev): string
    {
        global $DB;
        if (!self::crmAvailable()) {
            return 'O plugin CRM não está ativo neste GLPI: ative-o para enviar os leads.';
        }
        $pending = self::pending($ev->getID());
        if (!$pending) {
            return 'Não há leads novos neste evento para enviar ao CRM.';
        }
        $sent = 0;
        $known = 0;
        $f = $ev->fields;
        foreach ($pending as $r) {
            try {
                [$crmId, $new] = \GlpiPlugin\Crm\Lead::receive([
                    'event_id' => $ev->getID(), 'event_code' => $f['code'], 'event_name' => $f['name'], 'event_date' => $f['date_start'],
                    'name' => $r['title'], 'company' => $r['supplier'], 'email' => $r['doc_ref'], 'phone' => $r['code'], 'job_title' => $r['cc'],
                    'interest' => $r['category'], 'temperature' => $r['subcategory'], 'notes' => $r['detail'], 'date_creation' => $r['date_creation'],
                    '_alert' => true,
                ]);
            } catch (Throwable $e) {
                continue;
            }
            $DB->update(Registro::TABLE, ['status' => self::SENT, 'extra' => json_encode(['crm_lead_id' => $crmId]), 'date_mod' => date('Y-m-d H:i:s')], ['id' => $r['id']]);
            $new ? $sent++ : $known++;
        }
        Historico::record($ev->getID(), null, 'change', "Leads enviados ao CRM: $sent novo(s)" . ($known ? ", $known já existia(m)" : '') . '.');
        return "$sent lead(s) enviado(s) ao CRM" . ($known ? " ($known já estava(m) lá)" : '') . '. Eles aparecem em CRM > Gestão de Leads.';
    }

    /** Cartão da aba Marketing: quantos leads esperam envio e o botão para mandá-los ao CRM. */
    public static function card(Evento $ev, bool $edit): string
    {
        $e = [Menu::class, 'e'];
        $all = Registro::countKind($ev->getID(), 'lead_contato');
        $pending = count(self::pending($ev->getID()));
        $ok = self::crmAvailable();
        $back = Evento::tabUrl($ev->getID(), 'marketing');
        $btn = $edit && $pending && $ok
            ? "<form method='post' action='" . $e(Evento::getFormURL()) . "' class='m-0'><input type='hidden' name='id' value='" . $ev->getID() . "'><input type='hidden' name='_back' value='" . $e($back) . "'>"
                . "<button class='btn btn-primary' name='enviar_leads_crm' value='1' onclick=\"return confirm('Enviar $pending lead(s) deste evento para o CRM?')\"><i class='ti ti-send me-1'></i>Enviar leads para o CRM</button>" . \Html::closeForm(false)
            : "<button class='btn btn-outline-secondary' disabled><i class='ti ti-send me-1'></i>Enviar leads para o CRM</button>";
        $msg = !$ok ? 'O plugin CRM não está ativo: ative-o para enviar.' : ($pending ? "$pending de $all lead(s) ainda não foram enviados ao CRM." : ($all ? 'Todos os leads deste evento já estão no CRM.' : 'Cadastre os leads captados no evento (ou importe) e envie ao CRM.'));
        return "<div class='card mb-3'><div class='card-body d-flex flex-wrap align-items-center gap-3'><div class='flex-grow-1'><div class='fw-bold'><i class='ti ti-user-star me-1'></i>Leads do evento</div>"
            . "<div class='small text-secondary'>" . $e($msg) . " Na Gestão de Leads do CRM, o Comercial contata, qualifica e converte em oportunidade.</div></div>$btn</div></div>";
    }
}
