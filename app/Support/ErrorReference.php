<?php

namespace App\Support;

use Illuminate\Support\Str;

class ErrorReference
{
    /**
     * Public messages must not disclose implementation details.
     *
     * @var array<int, array{code: string, title: string, message: string}>
     */
    private const REFERENCES = [
        401 => [
            'code' => 'CAT-401-AUTENTICACAO',
            'title' => 'Acesso não autenticado',
            'message' => 'Entre novamente para continuar com segurança.',
        ],
        403 => [
            'code' => 'CAT-403-ACESSO',
            'title' => 'Acesso não permitido',
            'message' => 'Seu perfil não possui permissão para acessar esta área ou realizar esta ação.',
        ],
        404 => [
            'code' => 'CAT-404-RECURSO',
            'title' => 'Conteúdo não encontrado',
            'message' => 'A página ou o registro solicitado não existe, foi removido ou não está disponível para sua empresa.',
        ],
        419 => [
            'code' => 'CAT-419-SESSAO',
            'title' => 'Sessão expirada',
            'message' => 'Sua sessão de segurança expirou. Atualize a página, entre novamente se necessário e repita a ação.',
        ],
        429 => [
            'code' => 'CAT-429-LIMITE',
            'title' => 'Muitas tentativas',
            'message' => 'O limite temporário de tentativas foi atingido. Aguarde alguns instantes antes de tentar novamente.',
        ],
        500 => [
            'code' => 'CAT-500-INTERNO',
            'title' => 'Não foi possível concluir',
            'message' => 'Ocorreu uma falha inesperada. Tente novamente e, se o problema continuar, informe o protocolo ao suporte.',
        ],
        503 => [
            'code' => 'CAT-503-INDISPONIVEL',
            'title' => 'Serviço temporariamente indisponível',
            'message' => 'A plataforma está passando por manutenção ou indisponibilidade temporária. Tente novamente em alguns minutos.',
        ],
    ];

    public static function supports(int $status): bool
    {
        return isset(self::REFERENCES[$status]);
    }

    /** @return array{code: string, title: string, message: string} */
    public static function forStatus(int $status): array
    {
        return self::REFERENCES[$status] ?? self::REFERENCES[500];
    }

    public static function protocol(): string
    {
        return Str::upper(Str::random(10));
    }
}
