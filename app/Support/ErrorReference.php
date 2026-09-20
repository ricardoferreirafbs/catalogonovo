<?php

namespace App\Support;

use Illuminate\Support\Str;

class ErrorReference
{
    /**
     * Public messages must not disclose implementation details.
     *
     * @var array<int, array{code: string, title: string, message: string, meaning: string, actions: list<string>}>
     */
    private const REFERENCES = [
        401 => [
            'code' => 'CAT-401-AUTENTICACAO',
            'title' => 'Acesso não autenticado',
            'message' => 'Entre novamente para continuar com segurança.',
            'meaning' => 'A solicitação não possui uma autenticação válida ou a identificação do usuário não pôde ser confirmada.',
            'actions' => [
                'Acesse novamente a página de entrada e informe suas credenciais.',
                'Conclua a confirmação por MFA, quando solicitada.',
                'Se o problema continuar, remova apenas os cookies deste site e tente novamente.',
            ],
        ],
        403 => [
            'code' => 'CAT-403-ACESSO',
            'title' => 'Acesso não permitido',
            'message' => 'Seu perfil não possui permissão para acessar esta área ou realizar esta ação.',
            'meaning' => 'O usuário está autenticado, mas seu papel não possui a permissão exigida para a área ou ação.',
            'actions' => [
                'Confirme se você entrou na empresa e na conta corretas.',
                'Solicite ao Proprietário ou Administrador que confira o seu papel.',
                'Lembre-se: somente o Proprietário pode atribuir ou transferir o papel de Proprietário.',
            ],
        ],
        404 => [
            'code' => 'CAT-404-RECURSO',
            'title' => 'Conteúdo não encontrado',
            'message' => 'A página ou o registro solicitado não existe, foi removido ou não está disponível para sua empresa.',
            'meaning' => 'O endereço não corresponde a um conteúdo disponível. Por segurança, a plataforma não confirma se um registro pertence a outra empresa.',
            'actions' => [
                'Confira se o endereço foi digitado ou copiado corretamente.',
                'Volte ao painel e abra o item pela listagem atualizada.',
                'Confirme com o responsável se o registro ainda existe.',
            ],
        ],
        419 => [
            'code' => 'CAT-419-SESSAO',
            'title' => 'Sessão expirada',
            'message' => 'Sua sessão de segurança expirou. Atualize a página, entre novamente se necessário e repita a ação.',
            'meaning' => 'O token que protege o formulário expirou ou não corresponde mais à sessão atual.',
            'actions' => [
                'Atualize a página antes de preencher o formulário novamente.',
                'Entre novamente caso a plataforma solicite autenticação.',
                'Evite enviar um formulário que permaneceu aberto por muitas horas.',
            ],
        ],
        429 => [
            'code' => 'CAT-429-LIMITE',
            'title' => 'Muitas tentativas',
            'message' => 'O limite temporário de tentativas foi atingido. Aguarde alguns instantes antes de tentar novamente.',
            'meaning' => 'A proteção contra abuso limitou temporariamente novas solicitações, como tentativas de login ou MFA.',
            'actions' => [
                'Pare de repetir a solicitação e aguarde alguns minutos.',
                'Depois do intervalo, faça apenas uma nova tentativa.',
                'Se não reconhecer as tentativas, altere sua senha e comunique o responsável pela empresa.',
            ],
        ],
        500 => [
            'code' => 'CAT-500-INTERNO',
            'title' => 'Não foi possível concluir',
            'message' => 'Ocorreu uma falha inesperada. Tente novamente e, se o problema continuar, informe o protocolo ao suporte.',
            'meaning' => 'A plataforma encontrou uma condição inesperada e interrompeu a operação de forma segura.',
            'actions' => [
                'Tente realizar a ação novamente uma única vez.',
                'Se o erro persistir, anote o código e o protocolo mostrados na tela.',
                'Informe ao suporte a data, o horário aproximado e a ação realizada, sem enviar senha ou código MFA.',
            ],
        ],
        503 => [
            'code' => 'CAT-503-INDISPONIVEL',
            'title' => 'Serviço temporariamente indisponível',
            'message' => 'A plataforma está passando por manutenção ou indisponibilidade temporária. Tente novamente em alguns minutos.',
            'meaning' => 'A plataforma ou um serviço necessário está temporariamente indisponível para atendimento.',
            'actions' => [
                'Aguarde alguns minutos antes de atualizar a página.',
                'Não repita continuamente a mesma operação.',
                'Se a indisponibilidade continuar, consulte o canal oficial de atendimento.',
            ],
        ],
    ];

    public static function supports(int $status): bool
    {
        return isset(self::REFERENCES[$status]);
    }

    /** @return array{code: string, title: string, message: string, meaning: string, actions: list<string>} */
    public static function forStatus(int $status): array
    {
        return self::REFERENCES[$status] ?? self::REFERENCES[500];
    }

    public static function protocol(): string
    {
        return Str::upper(Str::random(10));
    }

    /** @return array<int, array{status: int, code: string, title: string, message: string, meaning: string, actions: list<string>}> */
    public static function all(): array
    {
        return array_map(
            fn (array $reference, int $status): array => ['status' => $status, ...$reference],
            self::REFERENCES,
            array_keys(self::REFERENCES),
        );
    }
}
