<?php

use App\Livewire\ManageFaqs;
use App\Models\Faq;
use App\Models\FaqSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** Create a staff user (has both 'view dashboard' and 'manage faqs'). */
function staffUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('staff');

    return $user;
}

/*
|--------------------------------------------------------------------------
| Public page
|--------------------------------------------------------------------------
*/

test('the public faq page is reachable by guests', function () {
    $this->get(route('faq.index'))->assertStatus(200);
});

test('published faqs are shown publicly and drafts are hidden', function () {
    Faq::create([
        'category' => 'Training',
        'question' => 'A published question?',
        'answer' => 'Visible answer.',
        'is_published' => true,
    ]);

    Faq::create([
        'category' => 'Training',
        'question' => 'A draft question?',
        'answer' => 'Hidden answer.',
        'is_published' => false,
    ]);

    $this->get(route('faq.index'))
        ->assertSee('A published question?')
        ->assertDontSee('A draft question?');
});

test('the public page falls back to the default heading when none is set', function () {
    $this->get(route('faq.index'))->assertSee('Frequently Asked Questions');
});

/*
|--------------------------------------------------------------------------
| Admin access control
|--------------------------------------------------------------------------
*/

test('guests cannot access the faq management page', function () {
    $this->get(route('admin.faqs.index'))->assertStatus(403);
});

test('a user without the manage faqs permission cannot access management', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view dashboard'); // can reach /admin, but not faqs

    $this->actingAs($user)
        ->get(route('admin.faqs.index'))
        ->assertStatus(403);
});

test('staff can access the faq management page', function () {
    $this->actingAs(staffUser())
        ->get(route('admin.faqs.index'))
        ->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Management component behaviour
|--------------------------------------------------------------------------
*/

test('staff can create a faq', function () {
    $this->actingAs(staffUser());

    Livewire::test(ManageFaqs::class)
        ->call('create')
        ->set('category', 'Training')
        ->set('question', 'How long until I get a mentor?')
        ->set('answer', 'It depends on availability.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Faq::where('question', 'How long until I get a mentor?')->exists())->toBeTrue();
});

test('creating a faq requires a question and answer', function () {
    $this->actingAs(staffUser());

    Livewire::test(ManageFaqs::class)
        ->call('create')
        ->set('question', '')
        ->set('answer', '')
        ->call('save')
        ->assertHasErrors(['question', 'answer']);
});

test('staff can toggle publish state and delete a faq', function () {
    $this->actingAs(staffUser());

    $faq = Faq::create([
        'category' => 'General Info',
        'question' => 'Toggle me?',
        'answer' => 'Body.',
        'is_published' => true,
    ]);

    $component = Livewire::test(ManageFaqs::class);

    $component->call('togglePublished', $faq->id);
    expect($faq->fresh()->is_published)->toBeFalse();

    $component->call('delete', $faq->id);
    expect(Faq::find($faq->id))->toBeNull();
});

test('reordering swaps the sort order within a category', function () {
    $this->actingAs(staffUser());

    $first = Faq::create(['category' => 'Training', 'question' => 'First', 'answer' => 'a', 'sort_order' => 1, 'is_published' => true]);
    $second = Faq::create(['category' => 'Training', 'question' => 'Second', 'answer' => 'b', 'sort_order' => 2, 'is_published' => true]);

    Livewire::test(ManageFaqs::class)->call('moveUp', $second->id);

    expect($second->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2);
});

test('staff can rename a category across all its faqs', function () {
    $this->actingAs(staffUser());

    Faq::create(['category' => 'Old Name', 'question' => 'Q1', 'answer' => 'a', 'is_published' => true]);
    Faq::create(['category' => 'Old Name', 'question' => 'Q2', 'answer' => 'b', 'is_published' => true]);

    Livewire::test(ManageFaqs::class)
        ->call('startRename', 'Old Name')
        ->set('categoryNewName', 'New Name')
        ->call('saveRename')
        ->assertHasNoErrors();

    expect(Faq::where('category', 'Old Name')->count())->toBe(0)
        ->and(Faq::where('category', 'New Name')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Page header settings
|--------------------------------------------------------------------------
*/

test('staff can edit the public page header and it appears publicly', function () {
    $this->actingAs(staffUser());

    Livewire::test(ManageFaqs::class)
        ->set('editingHeader', true)
        ->set('pageHeading', 'vZJX Help Center')
        ->set('pageIntro', 'Everything new controllers need.')
        ->call('savePageHeader')
        ->assertHasNoErrors();

    expect(FaqSetting::get('faq_heading'))->toBe('vZJX Help Center');

    $this->get(route('faq.index'))
        ->assertSee('vZJX Help Center')
        ->assertSee('Everything new controllers need.');
});

/*
|--------------------------------------------------------------------------
| Answer rendering
|--------------------------------------------------------------------------
*/

test('answer html authored in the editor is rendered on the public page', function () {
    Faq::create([
        'category' => 'Training',
        'question' => 'Where do I find the academy?',
        'answer' => '<p>Check the <a href="https://academy.vatusa.net">Academy</a>.</p>',
        'is_published' => true,
    ]);

    $this->get(route('faq.index'))
        ->assertSee('<a href="https://academy.vatusa.net">Academy</a>', false);
});
