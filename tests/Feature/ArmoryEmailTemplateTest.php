<?php

namespace Tests\Feature;

use App\Enums\ArmoryEmailTemplateCategory;
use App\Enums\ArmoryEmailTemplateStatus;
use App\Enums\UserRole;
use App\Models\ArmoryEmailTemplate;
use App\Models\ArmoryEmailTemplateAttachment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArmoryEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_browse_and_search_email_templates(): void
    {
        ArmoryEmailTemplate::factory()->create([
            'name' => 'Surplus First Mailer Follow-up',
            'subject' => 'Following up about surplus funds',
        ]);
        ArmoryEmailTemplate::factory()->create(['name' => 'Buyer Availability Notice']);
        $user = User::factory()->create(['role' => UserRole::ReadOnly]);

        $this->actingAs($user)->get(route('armory.email-templates.index', ['search' => 'surplus funds']))
            ->assertOk()
            ->assertSee('Surplus First Mailer Follow-up')
            ->assertDontSee('Buyer Availability Notice');
    }

    public function test_email_template_columns_are_sortable_and_keep_filters(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        ArmoryEmailTemplate::factory()->create([
            'name' => 'Zulu Surplus Template',
            'category' => ArmoryEmailTemplateCategory::SurplusOutreach,
            'status' => ArmoryEmailTemplateStatus::Draft,
            'subject' => 'Zulu subject',
        ]);
        ArmoryEmailTemplate::factory()->create([
            'name' => 'Alpha Surplus Template',
            'category' => ArmoryEmailTemplateCategory::SurplusOutreach,
            'status' => ArmoryEmailTemplateStatus::Active,
            'subject' => 'Alpha subject',
        ]);

        $response = $this->actingAs($user)->get(route('armory.email-templates.index', [
            'category' => ArmoryEmailTemplateCategory::SurplusOutreach->value,
            'sort' => 'template',
            'direction' => 'asc',
        ]));

        $response->assertOk()
            ->assertSeeInOrder(['Alpha Surplus Template', 'Zulu Surplus Template'])
            ->assertSee('sort=template', false)
            ->assertSee('direction=desc', false)
            ->assertSee('category=surplus_outreach', false);

        $this->get(route('armory.email-templates.index', [
            'sort' => 'status',
            'direction' => 'asc',
        ]))->assertOk()->assertSeeInOrder(['Alpha Surplus Template', 'Zulu Surplus Template']);
    }

    public function test_email_template_sorting_is_allowlisted(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('armory.email-templates.index', [
            'sort' => 'updated_at desc; drop table armory_email_templates',
            'direction' => 'asc',
        ]))->assertSessionHasErrors('sort');
    }

    public function test_authorized_user_can_create_audited_template_with_merge_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);

        $response = $this->actingAs($user)->post(route('armory.email-templates.save'), [
            'name' => 'Seller Property Follow-up',
            'category' => ArmoryEmailTemplateCategory::SellerOutreach->value,
            'status' => ArmoryEmailTemplateStatus::Active->value,
            'version_label' => '1.0',
            'description' => 'Follow up after initial seller contact.',
            'subject' => 'Following up about {{property_address}}',
            'body_text' => "Hello {{first_name}},\n\nI am following up about {{property_address}}.",
        ]);

        $template = ArmoryEmailTemplate::query()->firstOrFail();
        $response->assertRedirect(route('armory.email-templates.index'));
        $response->assertSessionHas('success', 'Email template “Seller Property Follow-up” was saved successfully.');
        $this->assertSame($user->id, $template->created_by);
        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'created',
            'auditable_type' => $template->getMorphClass(),
            'auditable_id' => $template->id,
        ]);
    }

    public function test_email_template_uses_safe_metadata_defaults_and_appears_in_library(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($user)->post(route('armory.email-templates.save'), [
            'name' => '  PreTax Owner Follow-up  ',
            'subject' => '  Following up about your property  ',
            'body_text' => 'Hello {{first_name}}, this is a follow-up.',
        ]);

        $template = ArmoryEmailTemplate::query()->firstOrFail();

        $response->assertRedirect(route('armory.email-templates.index'));
        $this->assertSame('PreTax Owner Follow-up', $template->name);
        $this->assertSame('Following up about your property', $template->subject);
        $this->assertSame(ArmoryEmailTemplateCategory::Other, $template->category);
        $this->assertSame(ArmoryEmailTemplateStatus::Draft, $template->status);
        $this->assertSame('1.0', $template->version_label);

        $this->actingAs($user)->get(route('armory.email-templates.index'))
            ->assertOk()
            ->assertSee('PreTax Owner Follow-up');
    }

    public function test_create_form_defaults_category_and_uses_server_side_validation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->get(route('armory.email-templates.create'))
            ->assertOk()
            ->assertSee('novalidate', false)
            ->assertSee('value="other" selected', false)
            ->assertSee('action="/armory/email-templates/save"', false)
            ->assertSee('form="email-template-form"', false)
            ->assertSee('formmethod="POST"', false)
            ->assertSee('formaction="/armory/email-templates/save"', false)
            ->assertSee('name="save_email_template"', false)
            ->assertSee('Insert a hyperlink')
            ->assertSee('name="attachments[]"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_template_can_store_private_reusable_attachment_and_safe_hyperlink(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->post(route('armory.email-templates.save'), [
            'name' => 'Offer Package',
            'category' => ArmoryEmailTemplateCategory::OffersAndContracts->value,
            'status' => ArmoryEmailTemplateStatus::Active->value,
            'version_label' => '1.0',
            'subject' => 'Your offer package',
            'body_text' => '[Review the property](https://valorventure.us/property/123)',
            'attachments' => [UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $template = ArmoryEmailTemplate::query()->firstOrFail();
        $attachment = ArmoryEmailTemplateAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($user)->get(route('armory.email-templates.show', $template))
            ->assertOk()
            ->assertSee('<a href="https://valorventure.us/property/123">Review the property</a>', false)
            ->assertSee('offer.pdf');
    }

    public function test_email_template_accepts_human_readable_version_labels(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->post(route('armory.email-templates.save'), [
            'name' => 'Surplus Outreach Letter',
            'category' => ArmoryEmailTemplateCategory::SurplusOutreach->value,
            'status' => ArmoryEmailTemplateStatus::Active->value,
            'version_label' => 'Version 1 - Approved',
            'subject' => 'Surplus funds follow-up',
            'body_text' => 'Hello {{first_name}}.',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('armory_email_templates', [
            'name' => 'Surplus Outreach Letter',
            'version_label' => 'Version 1 - Approved',
        ]);
    }

    public function test_template_content_strips_raw_html_when_displayed(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $template = ArmoryEmailTemplate::factory()->create([
            'body_text' => '<script>alert("unsafe")</script> Hello {{first_name}}',
        ]);

        $this->actingAs($user)->get(route('armory.email-templates.show', $template))
            ->assertOk()
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertDontSee('alert("unsafe")')
            ->assertSee('Hello {{first_name}}')
            ->assertSee('No email is sent from this screen.');
    }

    public function test_read_only_and_virtual_assistant_users_cannot_manage_templates(): void
    {
        $template = ArmoryEmailTemplate::factory()->create();

        foreach ([UserRole::ReadOnly, UserRole::VirtualAssistant] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('armory.email-templates.show', $template))->assertOk();
            $this->actingAs($user)->get(route('armory.email-templates.create'))->assertForbidden();
            $this->actingAs($user)->put(route('armory.email-templates.update', $template), [])->assertForbidden();
            $this->actingAs($user)->delete(route('armory.email-templates.destroy', $template))->assertForbidden();
        }

        $this->assertNotSoftDeleted($template);
    }

    public function test_required_email_fields_are_validated(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($user)->post(route('armory.email-templates.store'), [])
            ->assertSessionHasErrors(['name', 'category', 'status', 'version_label', 'subject', 'body_text']);
    }

    public function test_authorized_user_can_update_and_archive_a_template(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $template = ArmoryEmailTemplate::factory()->create();

        $this->actingAs($user)->put(route('armory.email-templates.update', $template), [
            'name' => 'Updated Template',
            'category' => ArmoryEmailTemplateCategory::FollowUp->value,
            'status' => ArmoryEmailTemplateStatus::Draft->value,
            'version_label' => '2.0',
            'subject' => 'Updated subject',
            'body_text' => 'Updated body',
        ])->assertRedirect(route('armory.email-templates.show', $template));

        $this->assertDatabaseHas('armory_email_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->delete(route('armory.email-templates.destroy', $template))
            ->assertRedirect(route('armory.email-templates.index'));
        $this->assertSoftDeleted($template);
    }
}
