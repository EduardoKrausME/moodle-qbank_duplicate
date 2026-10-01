<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * qbank_duplicate.php
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


$string['classification:related_but_distinct'] = 'Relacionadas, mas distintas';
$string['classification:same_question'] = 'Mesma questão';
$string['classification:strongly_overlapping'] = 'Sobreposição forte';
$string['classification:unrelated'] = 'Não relacionadas';
$string['confidencevalue'] = 'Confiança da IA: {$a}%';
$string['currentcategory'] = 'Categoria atual do banco de questões: {$a}';
$string['decisionsaved'] = 'A decisão sobre o par foi salva.';
$string['error:invalidairesponse'] = 'O AI Bridge retornou uma resposta inválida para a comparação de duplicidade.';
$string['error:stalepair'] = 'O par candidato mudou ou não está mais disponível nesta categoria.';
$string['heuristicvalue'] = 'Heurística local: {$a}%';
$string['hideunrelated'] = 'Ocultar não relacionadas e erros de IA';
$string['ignorepair'] = 'Ignorar par';
$string['marknotduplicate'] = 'Marcar como não duplicada';
$string['nopairs'] = 'Nenhum par duplicado relevante foi encontrado na análise mais recente.';
$string['noscans'] = 'Ainda não existe análise de duplicidade para esta categoria.';
$string['phase:candidates'] = 'Gerando candidatos';
$string['phase:done'] = 'Concluído';
$string['phase:failed'] = 'Falhou';
$string['phase:queued'] = 'Na fila';
$string['phase:semantic'] = 'Comparação semântica';
$string['phase:snapshot'] = 'Normalizando versões das questões';
$string['pluginname'] = 'Questões duplicadas';
$string['privacy:metadata'] = 'O plugin de questões duplicadas não armazena dados pessoais de usuários. Ele armazena apenas metadados de análise do banco de questões.';
$string['questiona'] = 'Questão A';
$string['questionb'] = 'Questão B';
$string['reason'] = 'Motivo';
$string['reportintro'] = 'Encontra duplicatas exatas e semânticas sem comparar todas as combinações possíveis. A geração de candidatos ocorre localmente primeiro e a IA é usada apenas nos pares candidatos limitados. Nada é excluído automaticamente.';
$string['scanqueued'] = 'A análise de duplicidade foi enfileirada.';
$string['scanstatus'] = 'Status: {$a->status}. Etapa: {$a->phase}. Questões: {$a->questions}. Pares candidatos: {$a->candidates}. Chamadas de IA: {$a->aicalls}. Erros de IA: {$a->aierrors}.';
$string['setting:comparebatchsize'] = 'Pares por tarefa adhoc';
$string['setting:comparebatchsize_desc'] = 'Quantidade de pares candidatos processados por cada tarefa adhoc de comparação semântica.';
$string['setting:maxaipairs'] = 'Máximo de novos pares de IA por análise';
$string['setting:maxaipairs_desc'] = 'Limite rígido de novos pares candidatos que podem exigir IA em uma análise de categoria. Pares já analisados e inalterados são reutilizados sem consumir este limite.';
$string['setting:maxbucket'] = 'Tamanho máximo de bucket não exato';
$string['setting:maxbucket_desc'] = 'Buckets de LSH/palavras-chave maiores que este valor são considerados comuns demais e ignorados. Buckets com texto normalizado idêntico usam uma cadeia conectada de N−1 pares em vez de N².';
$string['setting:maxcandidatesperquestion'] = 'Máximo de candidatos por questão';
$string['setting:maxcandidatesperquestion_desc'] = 'Limita quantos candidatos semânticos ativos uma questão pode produzir em uma análise.';
$string['showunrelated'] = 'Mostrar não relacionadas e erros de IA';
$string['similarity'] = 'Similaridade';
$string['startscan'] = 'Analisar esta categoria';
$string['status:aierror'] = 'Erro na comparação por IA';
$string['status:comparing'] = 'Comparando candidatos';
$string['status:completed'] = 'Concluído';
$string['status:completedwitherrors'] = 'Concluído com erros de IA';
$string['status:failed'] = 'Falhou';
$string['status:queued'] = 'Na fila';
$string['status:running'] = 'Executando';
$string['task:compare'] = 'Comparar candidatos a questões duplicadas com IA';
$string['task:scan'] = 'Analisar categoria em busca de questões duplicadas';
