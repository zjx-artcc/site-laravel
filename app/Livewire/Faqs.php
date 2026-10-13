<?php

namespace App\Livewire;

use App\Models\Faq;
use App\Models\FaqSetting;
use Livewire\Attributes\Url;
use Livewire\Component;

class Faqs extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function render()
    {
        $query = Faq::query()->published()->ordered();

        $search = trim($this->search);
        if ($search !== '') {
            $words = preg_split('/\s+/', mb_strtolower($search));

            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->whereRaw('LOWER(question) LIKE ?', ["%{$word}%"])
                            ->orWhereRaw('LOWER(answer) LIKE ?', ["%{$word}%"])
                            ->orWhereRaw('LOWER(category) LIKE ?', ["%{$word}%"]);
                    });
                }
            });
        }

        $faqs = $query->get();

        return view('livewire.faqs', [
            'groupedFaqs' => $faqs->groupBy('category'),
            'lastUpdated' => Faq::published()->max('updated_at'),
            'heading' => FaqSetting::get('faq_heading'),
            'intro' => FaqSetting::get('faq_intro'),
        ]);
    }
}
