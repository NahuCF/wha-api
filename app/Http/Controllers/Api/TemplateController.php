<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactFieldType;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Resources\BroadcastResource;
use App\Http\Resources\TemplateResource;
use App\Models\ContactField;
use App\Models\Template;
use App\Services\MetaService;
use App\Services\TemplateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
        ]);

        $status = data_get($input, 'status');
        $rowsPerPage = data_get($input, 'rows_per_page', 10);
        $name = data_get($input, 'name');

        $templates = Template::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($name, fn ($q) => $q->where('name', 'ILIKE', "%{$name}%"))
            ->orderBy('id', 'desc')
            ->paginate($rowsPerPage);

        $templatesCount = Template::query()
            ->count();

        return TemplateResource::collection($templates)->additional([
            'meta' => [
                'templates_count' => $templatesCount,
            ],
        ]);
    }

    public function store(StoreTemplateRequest $request)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $language = data_get($input, 'language');
        $category = data_get($input, 'category');
        $header = data_get($input, 'components.header', null);
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = data_get($input, 'components.buttons', []);

        $variables = collect(data_get($body, 'variables', []));

        $variables = $variables->map(function ($variable) {
            $contactFieldId = data_get($variable, 'contact_field_id');

            if (! $contactFieldId) {
                $name = data_get($variable, 'name');

                $contactField = ContactField::query()
                    ->create([
                        'name' => $name,
                        'internal_name' => str_replace(' ', '_', strtolower($name)),
                        'type' => ContactFieldType::TEXT,
                    ]);

                $variable['contact_field_id'] = $contactField->id;
            }

            return $variable;
        });

        $templateWithLanguageAndName = Template::query()
            ->where('name', $name)
            ->where('language', $language)
            ->exists();

        if ($templateWithLanguageAndName) {
            throw new HttpResponseException(response()->json([
                'message' => 'Template name with language already exists',
                'message_code' => 'template_name_language_already_exists',
            ], 422));
        }

        $template = Template::create([
            'name' => $name,
            'language' => $language,
            'category' => strtoupper($category),
            'body' => $body['text'],
            'body_example_variables' => json_encode($variables->toArray()),
            'footer' => $footer,
            'header' => $header ? json_encode($header) : null,
            'buttons' => json_encode($buttons),
            'status' => AppEnvironment::isProduction() ? TemplateStatus::PENDING : TemplateStatus::APPROVED,
            'updated_count_while_approved' => 0,
        ]);

        if (AppEnvironment::isProduction()) {
            $response = (new MetaService)->createTemplate(
                $name,
                TemplateCategory::from($category),
                $language,
                (new TemplateService)->templateComponentsToMeta($template)
            );

            $template->update([
                'meta_id' => $response['id'],
                'status' => $response['status'],
                'category' => $response['category'],
            ]);
        }

        return TemplateResource::make($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $language = data_get($input, 'language');
        $category = data_get($input, 'category');
        $header = data_get($input, 'components.header');
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = data_get($input, 'components.buttons', []);

        $variables = collect(data_get($body, 'variables', []));

        if ($template->days_since_meta_update >= 30) {
            $template->update(['updated_count_while_approved' => 0]);
        }

        if ($template->days_since_meta_update < 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'Update tries exceeded',
                'message_code' => 'template_daily_update_limit_reached',
            ], 422));
        }

        if ($template->days_since_meta_update < 30 && $template->updated_count_while_approved == 10) {
            throw new HttpResponseException(response()->json([
                'message' => 'Update tries exceeded',
                'message_code' => 'template_monthly_update_limit_reached',
            ], 422));
        }

        if ($template->status == TemplateStatus::APPROVED) {
            if ($name != $template->name || $language != $template->language || $category != $template->category) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Template cannot be updated',
                    'message_code' => 'invalid_fields_to_update',
                ]));
            }
        } else {
            if ($name != $template->name || $language != $template->language) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Template cannot be updated',
                    'message_code' => 'invalid_fields_to_update',
                ]));
            }
        }

        $variables = $variables->map(function ($variable) {
            $contactFieldId = data_get($variable, 'contact_field_id');

            if (! $contactFieldId) {
                $name = data_get($variable, 'name');

                $contactField = ContactField::query()
                    ->create([
                        'name' => $name,
                        'internal_name' => str_replace(' ', '_', strtolower($name)),
                        'type' => ContactFieldType::TEXT,
                    ]);

                $variable['contact_field_id'] = $contactField->id;
            }

            return $variable;
        });

        $templateWithLanguageAndName = Template::query()
            ->where('name', $name)
            ->where('language', $language)
            ->where('id', '!=', $template->id)
            ->exists();

        if ($templateWithLanguageAndName) {
            throw new HttpResponseException(response()->json([
                'message' => 'Template name with language already exists',
                'message_code' => 'template_name_language_already_exists',
            ], 422));
        }

        $template->update([
            'name' => $name,
            'language' => $language,
            'category' => strtoupper($category),
            'body' => $body['text'],
            'body_example_variables' => json_encode($variables->toArray()),
            'footer' => $footer,
            'header' => json_encode($header ?? []),
            'buttons' => json_encode($buttons),
            'status' => 'PENDING',
            'updated_count_while_approved' => $template->updated_count_while_approved + 1,
            'meta_updated_at' => now(),
        ]);

        if (AppEnvironment::isProduction() && $template->meta_id) {
            $response = (new MetaService)->updateTemplate(
                $template->meta_id,
                (new TemplateService)->templateComponentsToMeta($template)
            );

            if (isset($response['success']) && $response['success']) {
                $template->update([
                    'status' => 'PENDING',
                ]);
            }
        }

        return TemplateResource::make($template);
    }

    public function show(Template $template)
    {
        return TemplateResource::make($template);
    }

    public function destroy(Template $template)
    {
        if ($template->tenant_id != tenant()->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        $activeBroadcasts = (new TemplateService)->getActiveBroadcasts($template);

        if ($activeBroadcasts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'active_broadcasts_count' => 'Template has active broadcasts',
            ]);
        }

        $template->delete();

        return response()->noContent();
    }

    public function activeBroadcasts(Template $template)
    {
        $activeBroadcasts = (new TemplateService)->getActiveBroadcasts($template);

        return BroadcastResource::collection($activeBroadcasts);
    }
}
