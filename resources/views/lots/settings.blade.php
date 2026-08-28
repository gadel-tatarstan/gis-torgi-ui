@extends('layouts.app')

@section('content')
<div x-data="settingsApp()" x-init="init()">
    <header class="bg-white shadow-sm">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center gap-3">
            <a href="{{ route('lots.index') }}" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <span class="material-icons-outlined text-gray-700">arrow_back</span>
            </a>
            <h1 class="text-lg font-semibold text-gray-800">Настройки</h1>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Интеграция с YouGile</h2>

            <!-- Success Message -->
            <template x-if="success">
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-4">
                    Настройки сохранены успешно!
                </div>
            </template>

            <!-- Error Message -->
            <template x-if="error">
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-4">
                    <span x-text="error"></span>
                </div>
            </template>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company ID</label>
                    <input type="text" x-model="form.yg_company_id" placeholder="ID компании в YouGile"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1">Найдите в URL: yougile.com/team/<strong>{company_id}</strong>/...</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Token</label>
                    <input type="password" x-model="form.yg_api_token" placeholder="Токен доступа к API"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1">Создайте в настройках YouGile → Интеграции → API</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Board ID (ID доски)</label>
                    <input type="text" x-model="form.yg_board_id" placeholder="ID доски в YouGile"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1">Найдите в URL при открытии доски</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Column ID (ID колонки)</label>
                    <input type="text" x-model="form.yg_column_id" placeholder="ID колонки в YouGile"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1">ID колонки, в которую будут добавляться задачи</p>
                </div>

                <button @click="saveSettings()" :disabled="saving"
                        class="w-full bg-blue-600 text-white py-2.5 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition mt-6">
                    <span x-show="!saving">Сохранить</span>
                    <span x-show="saving" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Сохранение...
                    </span>
                </button>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
function settingsApp() {
    return {                form: {
                    yg_company_id: '',
                    yg_api_token: '',
                    yg_board_id: '',
                    yg_column_id: '',
                },
        saving: false,
        success: false,
        error: null,

        init() {
            const setting = @js($setting->only(['yg_company_id', 'yg_api_token', 'yg_board_id', 'yg_column_id']));
            this.form.yg_company_id = setting.yg_company_id || '';
            this.form.yg_api_token = setting.yg_api_token || '';
            this.form.yg_board_id = setting.yg_board_id || '';
            this.form.yg_column_id = setting.yg_column_id || '';
        },

        async saveSettings() {
            this.saving = true;
            this.success = false;
            this.error = null;
            try {
                const res = await fetch('/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (data.success) {
                    this.success = true;
                    setTimeout(() => this.success = false, 3000);
                } else {
                    this.error = data.error || 'Ошибка сохранения';
                }
            } catch (e) {
                this.error = 'Ошибка подключения к серверу';
            }
            this.saving = false;
        },
    };
}
</script>
@endpush
