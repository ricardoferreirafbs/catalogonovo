<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContentController extends Controller
{
    public function edit()
    {
        $tenant = auth()->user()->tenant;
        return view('admin.content', ['tenant' => $tenant, 'content' => array_replace_recursive(self::defaults(), $tenant->content ?? [])]);
    }

    public function update(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $rules = [
            'contact_phone' => ['nullable', 'string', 'max:30'], 'logo' => ['nullable', 'image', 'max:2048'], 'hero_image' => ['nullable', 'image', 'max:6144'], 'hero_image_2' => ['nullable', 'image', 'max:6144'], 'hero_image_3' => ['nullable', 'image', 'max:6144'], 'experience_image' => ['nullable', 'image', 'max:6144'],
            'seo_title' => ['nullable', 'string', 'max:70'], 'seo_description' => ['nullable', 'string', 'max:170'],
            'hero_eyebrow' => ['nullable', 'string', 'max:80'], 'hero_title' => ['required', 'string', 'max:120'], 'hero_highlight' => ['nullable', 'string', 'max:80'], 'hero_text' => ['nullable', 'string', 'max:360'],
            'hero_primary_label' => ['nullable', 'string', 'max:40'], 'hero_primary_url' => ['nullable', 'string', 'max:255'], 'hero_secondary_label' => ['nullable', 'string', 'max:40'], 'hero_secondary_url' => ['nullable', 'string', 'max:255'],
            'hero_note_title' => ['nullable', 'string', 'max:80'], 'hero_note_text' => ['nullable', 'string', 'max:160'],
            'stat_1_value' => ['nullable', 'string', 'max:30'], 'stat_1_label' => ['nullable', 'string', 'max:50'], 'stat_2_value' => ['nullable', 'string', 'max:30'], 'stat_2_label' => ['nullable', 'string', 'max:50'], 'stat_3_value' => ['nullable', 'string', 'max:30'], 'stat_3_label' => ['nullable', 'string', 'max:50'],
            'marquee' => ['nullable', 'string', 'max:300'], 'catalog_eyebrow' => ['nullable', 'string', 'max:80'], 'catalog_title' => ['nullable', 'string', 'max:120'], 'catalog_text' => ['nullable', 'string', 'max:300'],
            'experience_eyebrow' => ['nullable', 'string', 'max:80'], 'experience_title' => ['nullable', 'string', 'max:140'], 'experience_text' => ['nullable', 'string', 'max:700'],
            'about_quote' => ['nullable', 'string', 'max:240'], 'about_button_label' => ['nullable', 'string', 'max:60'], 'about_button_url' => ['nullable', 'string', 'max:500'],
            'experience_item_1_title' => ['nullable', 'string', 'max:80'], 'experience_item_1_text' => ['nullable', 'string', 'max:240'], 'experience_item_2_title' => ['nullable', 'string', 'max:80'], 'experience_item_2_text' => ['nullable', 'string', 'max:240'], 'experience_item_3_title' => ['nullable', 'string', 'max:80'], 'experience_item_3_text' => ['nullable', 'string', 'max:240'],
            'steps_eyebrow' => ['nullable', 'string', 'max:80'], 'steps_title' => ['nullable', 'string', 'max:140'],
            'step_1_title' => ['nullable', 'string', 'max:80'], 'step_1_text' => ['nullable', 'string', 'max:240'], 'step_2_title' => ['nullable', 'string', 'max:80'], 'step_2_text' => ['nullable', 'string', 'max:240'], 'step_3_title' => ['nullable', 'string', 'max:80'], 'step_3_text' => ['nullable', 'string', 'max:240'],
            'contact_eyebrow' => ['nullable', 'string', 'max:80'], 'contact_title' => ['nullable', 'string', 'max:140'], 'contact_text' => ['nullable', 'string', 'max:400'], 'contact_button' => ['nullable', 'string', 'max:50'],
            'faq_eyebrow' => ['nullable', 'string', 'max:80'], 'faq_title' => ['nullable', 'string', 'max:120'], 'faq_1_question' => ['nullable', 'string', 'max:160'], 'faq_1_answer' => ['nullable', 'string', 'max:500'], 'faq_2_question' => ['nullable', 'string', 'max:160'], 'faq_2_answer' => ['nullable', 'string', 'max:500'], 'faq_3_question' => ['nullable', 'string', 'max:160'], 'faq_3_answer' => ['nullable', 'string', 'max:500'],
            'footer_text' => ['nullable', 'string', 'max:300'], 'footer_social_label' => ['nullable', 'string', 'max:80'], 'footer_social_url' => ['nullable', 'string', 'max:500'],
        ];
        $data = $request->validate($rules);
        $tenant->contact_phone = $data['contact_phone'] ?? null;
        unset($data['contact_phone'], $data['logo'], $data['hero_image'], $data['hero_image_2'], $data['hero_image_3'], $data['experience_image']);
        foreach (['show_stats', 'show_marquee', 'show_experience', 'show_steps', 'show_contact', 'show_faq'] as $field) $data[$field] = $request->boolean($field);
        $tenant->content = array_replace_recursive(self::defaults(), $data);
        $this->replaceImage($request, $tenant, 'logo', 'logo_path');
        $this->replaceImage($request, $tenant, 'hero_image', 'hero_image_path');
        $this->replaceImage($request, $tenant, 'hero_image_2', 'hero_image_2_path');
        $this->replaceImage($request, $tenant, 'hero_image_3', 'hero_image_3_path');
        $this->replaceImage($request, $tenant, 'experience_image', 'experience_image_path');
        $tenant->save();
        return back()->with('success', 'Conteúdo do template publicado.');
    }

    private function replaceImage(Request $request, $tenant, string $input, string $column): void
    {
        if (! $request->hasFile($input)) return;
        if ($tenant->{$column}) Storage::disk('uploads')->delete($tenant->{$column});
        $tenant->{$column} = $request->file($input)->store("tenants/{$tenant->id}/brand", 'uploads');
    }

    public static function defaults(): array
    {
        return [
            'seo_title' => '', 'seo_description' => '', 'hero_eyebrow' => 'CATÁLOGO DIGITAL', 'hero_title' => 'Produtos que merecem', 'hero_highlight' => 'ser descobertos.', 'hero_text' => 'Uma seleção apresentada com clareza, personalidade e atendimento próximo.',
            'hero_primary_label' => 'Ver coleção', 'hero_primary_url' => '#catalogo', 'hero_secondary_label' => 'Como funciona', 'hero_secondary_url' => '#como-pedir',
            'hero_note_title' => 'Feito especialmente para você', 'hero_note_text' => 'Cada detalhe pode contar uma história.',
            'show_stats' => true, 'stat_1_value' => 'Seleção', 'stat_1_label' => 'CURADORIA', 'stat_2_value' => 'Direto', 'stat_2_label' => 'ATENDIMENTO', 'stat_3_value' => 'Sempre', 'stat_3_label' => 'ATUALIZADO',
            'show_marquee' => true, 'marquee' => 'PRODUTOS SELECIONADOS • ATENDIMENTO DIRETO • CATÁLOGO ATUALIZADO • FEITO PARA DESCOBRIR',
            'catalog_eyebrow' => 'CATÁLOGO INTERATIVO', 'catalog_title' => 'Encontre o produto que combina com você.', 'catalog_text' => 'Filtre por categoria, pesquise pelo nome ou use o código.',
            'show_experience' => true, 'experience_eyebrow' => 'UMA EXPERIÊNCIA MELHOR', 'experience_title' => 'Da primeira impressão à escolha certa.', 'experience_text' => 'Organize sua coleção para facilitar a descoberta e transformar interesse em contato.',
            'about_quote' => 'Pequenos gestos podem criar grandes lembranças.', 'about_button_label' => 'Conheça mais', 'about_button_url' => '',
            'experience_item_1_title' => 'Escolha visual imediata', 'experience_item_1_text' => 'Fotos grandes e categorias claras facilitam a comparação.', 'experience_item_2_title' => 'Informações essenciais', 'experience_item_2_text' => 'Código, descrição e valor aparecem juntos.', 'experience_item_3_title' => 'Contato sem atrito', 'experience_item_3_text' => 'O visitante chega ao atendimento com o produto escolhido.',
            'show_steps' => true, 'steps_eyebrow' => 'JORNADA SIMPLES', 'steps_title' => 'Escolher é fácil. Pedir também deve ser.', 'step_1_title' => 'Explore', 'step_1_text' => 'Navegue pelos filtros e encontre seu produto.', 'step_2_title' => 'Escolha', 'step_2_text' => 'Abra os detalhes e confira as informações.', 'step_3_title' => 'Converse', 'step_3_text' => 'Envie sua escolha e confirme os próximos passos.',
            'show_contact' => true, 'contact_eyebrow' => 'ATENDIMENTO DIRETO', 'contact_title' => 'Gostou de um produto? Fale conosco.', 'contact_text' => 'Tire dúvidas, confirme disponibilidade, prazo e condições.', 'contact_button' => 'Iniciar conversa',
            'show_faq' => true, 'faq_eyebrow' => 'ANTES DE PEDIR', 'faq_title' => 'Dúvidas rápidas.', 'faq_1_question' => 'Como identifico o produto correto?', 'faq_1_answer' => 'Informe o nome e o código exibidos na página do produto.', 'faq_2_question' => 'Os valores estão atualizados?', 'faq_2_answer' => 'Os valores do catálogo são mantidos pela equipe responsável.', 'faq_3_question' => 'Como faço meu pedido?', 'faq_3_answer' => 'Use o botão de contato e envie os dados do produto escolhido.', 'footer_text' => 'Produtos apresentados com clareza e atendimento próximo.', 'footer_social_label' => 'Siga nossa marca', 'footer_social_url' => '',
        ];
    }
}
