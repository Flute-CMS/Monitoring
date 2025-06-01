<form>
    <x-forms.field class="mb-4">
        <x-fields.checkbox name="hide_inactive" id="hide_inactive" checked="{{ $settings['hide_inactive'] ?? false }}"
            label="{{ __('monitoring.settings.hide_inactive_servers') }}" />
    </x-forms.field>

    <x-forms.field class="mb-4">
        <x-fields.checkbox name="show_count_players" id="show_count_players" checked="{{ $settings['show_count_players'] ?? false }}"
            label="{{ __('monitoring.settings.show_count_players') }}" />
    </x-forms.field>

    <x-forms.field class="mb-4">
        <x-fields.checkbox name="show_placeholders" id="show_placeholders" checked="{{ $settings['show_placeholders'] ?? false }}"
            label="{{ __('monitoring.settings.show_placeholders') }}" />
    </x-forms.field>

    <x-forms.field class="mb-4"> 
        <x-forms.label for="limit">{{ __('monitoring.settings.servers_limit') }}</x-forms.label>
        <x-fields.input type="number" id="limit" name="limit" min="1" max="50" value="{{ $settings['limit'] ?? 5 }}" />
        <small class="form-text text-muted">{{ __('monitoring.settings.servers_limit_help') }}</small>
    </x-forms.field>

    <x-forms.field>
        <x-forms.label for="display_mode">{{ __('monitoring.settings.display_mode') }}</x-forms.label>
        <x-fields.select id="display_mode" name="display_mode">
            <option value="standard" {{ ($settings['display_mode'] ?? 'standard') == 'standard' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_standard') }}</option>
            <option value="compact" {{ ($settings['display_mode'] ?? 'standard') == 'compact' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_compact') }}</option>
            <option value="ultracompact" {{ ($settings['display_mode'] ?? 'standard') == 'ultracompact' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_ultracompact') }}</option>
            <option value="table" {{ ($settings['display_mode'] ?? 'standard') == 'table' ? 'selected' : '' }}>{{ __('monitoring.settings.display_mode_table') }}</option>
        </x-fields.select>
        <small class="form-text text-muted">{{ __('monitoring.settings.display_mode_help') }}</small>
    </x-forms.field>
</form>