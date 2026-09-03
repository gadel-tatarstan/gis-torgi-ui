@extends('layouts.app')

@section('content')
<div x-data="lotApp()" x-init="init()" x-cloak>
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="toggleSidebar()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                    <span class="material-icons-outlined text-gray-700">filter_list</span>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">Подбор земельных участков</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500" x-text="totalElements + ' ' + pluralize(totalElements, 'лот', 'лота', 'лотов')"></span>
                <a href="{{ route('lots.settings') }}" class="p-2 hover:bg-gray-100 rounded-lg transition" title="Настройки">
                    <span class="material-icons-outlined text-gray-700">settings</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Filter Sidebar -->
    <div x-show="sidebarOpen" x-cloak
         class="fixed inset-0 z-50 sidebar-overlay bg-black/30"
         @click.self="sidebarOpen = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed left-0 top-0 h-full w-80 bg-white shadow-xl p-6 overflow-y-auto transition-transform duration-300"
             x-show="sidebarOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold">Фильтры</h2>
                <button @click="sidebarOpen = false" class="p-1 hover:bg-gray-100 rounded">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена от (руб.)</label>
                    <input type="number" x-model.number="filters.price_min_from" min="0" step="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена до (руб.)</label>
                    <input type="number" x-model.number="filters.price_min_to" min="0" step="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата публикации от</label>
                    <input type="date" x-model="filters.pub_from" :max="filters.pub_to"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата публикации до</label>
                    <input type="date" x-model="filters.pub_to" :max="today"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <button @click="loadLots(1)" :disabled="loading"
                        class="w-full bg-blue-600 text-white py-2.5 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition mt-6">
                    <span x-show="!loading">Загрузить</span>
                    <span x-show="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Загрузка...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- Error -->
        <template x-if="error">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-center mb-4">
                <span x-text="error"></span>
            </div>
        </template>

        <!-- Loading -->
        <template x-if="loading && lots.length === 0">
            <div class="flex flex-col items-center justify-center py-20">
                <svg class="animate-spin h-10 w-10 text-blue-600 mb-4" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-gray-500">Загрузка данных...</p>
            </div>
        </template>

        <!-- Lot Cards -->
        <template x-if="!loading || lots.length > 0">
            <div class="relative">
                <!-- Page transition overlay -->
                <div x-show="loadingPage" x-transition.opacity class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center pointer-events-none">
                    <svg class="animate-spin h-8 w-8 text-blue-600" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <div class="space-y-2" :class="{ 'pointer-events-none opacity-60': loadingPage }">
                    <template x-for="lot in lots" :key="lot.id">
                        <div class="border border-gray-200 rounded-lg bg-white hover:shadow-md transition-all min-w-0 relative"
                             :class="{ 'lot-card-faded': lot.is_not_interested, 'lot-card-on-board': lot.on_board }"
                             @mousedown="_mdX = $event.clientX; _mdY = $event.clientY"
                             @click="if (Math.abs($event.clientX - _mdX) < 5 && Math.abs($event.clientY - _mdY) < 5) openLotModal(lot)">

                            <!-- Collapsed for not-interested -->
                            <div class="lot-card-collapsed hidden p-3 cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium text-gray-400 truncate shrink-0" x-text="lot.cadastral_number || 'Без номера'"></p>
                                    <p x-show="lot.comment" class="text-[10px] text-gray-600 leading-tight line-clamp-1 truncate" x-text="lot.comment"></p>
                                </div>
                            </div>

                            <!-- Full Content -->
                            <div class="flex items-center w-full">
                            <div class="lot-card-content flex items-center flex-nowrap flex-1 min-w-0 overflow-x-auto">
                                <!-- Thumbnail -->
                                <div class="w-20 h-20 shrink-0 bg-gray-100 relative cursor-pointer rounded-l-lg overflow-hidden">
                                    <template x-if="lot.lot_images && lot.lot_images.length > 0">
                                        <img :src="'https://torgi.gov.ru/new/image-preview/v1/' + lot.lot_images[0] + '?disposition=inline&resize=600x600!'"
                                             class="w-full h-full object-cover" loading="lazy"
                                             @@error="$event.target.style.display='none'">
                                    </template>
                                    <template x-if="!lot.lot_images || lot.lot_images.length === 0">
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <span class="material-icons-outlined text-2xl">image_not_supported</span>
                                        </div>
                                    </template>
                                    <!-- YouGile Badge -->
                                    <template x-if="lot.on_board">
                                        <div class="yougile-badge z-10">
                                            <img src="{{ asset('yougile2.avif') }}" alt="YouGile" class="w-full h-full object-cover">
                                        </div>
                                    </template>
                                    <!-- Viewed Badge -->
                                    <template x-if="lot.is_viewed && !lot.on_board">
                                        <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[9px] text-center py-0.5 z-10 whitespace-nowrap">Просмотрено</div>
                                    </template>
                                </div>

                                <!-- Cadastral + Price -->
                                <div class="shrink-0 px-3 py-2 min-w-[140px] cursor-pointer flex flex-col justify-center">
                                    <p class="text-xs font-medium text-gray-600 truncate" x-text="lot.cadastral_number || '—'"></p>
                                    <p class="text-sm font-bold text-green-700 whitespace-nowrap" x-text="formatPrice(lot.price_min)"></p>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0 px-3 py-2 text-xs text-gray-500 cursor-pointer hidden sm:flex flex-col justify-center items-start overflow-hidden">
                                    <span class="inline-flex items-center gap-0.5">
                                        <span class="material-icons-outlined text-xs">straighten</span>
                                        <span x-text="formatArea(lot.area)"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-0.5">
                                        <span class="material-icons-outlined text-xs">category</span>
                                        <span class="truncate" x-text="lot.permitted_use_name || '—'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-0.5">
                                        <span class="material-icons-outlined text-xs">store</span>
                                        <span class="truncate" x-text="etpNames[lot.etp_code] || lot.etp_code || '—'"></span>
                                    </span>
                                </div>

                                <!-- Comment -->
                                <div x-show="lot.comment" class="flex-1 min-w-0 px-3 py-2 cursor-pointer hidden sm:block">
                                    <p class="text-[10px] text-gray-400 leading-tight line-clamp-3" x-text="lot.comment"></p>
                                </div>

                                <!-- Date / Countdown -->
                                <div class="shrink-0 px-3 py-2 min-w-[110px] cursor-pointer flex flex-col justify-center">
                                    <template x-if="lot.bidd_end_time">
                                        <div>
                                            <div class="text-xs">
                                                <span class="text-gray-400" x-text="isExpired(lot.bidd_end_time) ? 'Истекло:' : 'Осталось:'"></span>
                                                <span :class="isExpired(lot.bidd_end_time) ? 'countdown-expired font-semibold' : 'text-blue-600 font-medium'"
                                                      x-text="getCountdown(lot.bidd_end_time)"></span>
                                            </div>
                                            <div class="text-[10px] text-gray-400 mt-0.5"
                                                 :class="{ 'countdown-expired': isExpired(lot.bidd_end_time) }"
                                                 x-text="formatDate(lot.bidd_end_time)"></div>
                                        </div>
                                    </template>
                                    <template x-if="!lot.bidd_end_time">
                                        <div class="text-xs text-gray-400">—</div>
                                    </template>
                                </div>

                            </div><!-- end overflow-x-auto -->
                                <!-- Actions (outside overflow) -->
                                <div x-show="!lot.is_not_interested" class="shrink-0 px-2 py-2 flex items-center" @click.stop>
                                    <div class="relative">
                                        <button @click="toggleDropdown(lot.id)" class="p-1.5 hover:bg-gray-100 rounded-full">
                                            <span class="material-icons-outlined text-gray-400 text-lg">more_vert</span>
                                        </button>
                                        <div x-show="openDropdownId === lot.id" @click.away="openDropdownId = null" x-cloak
                                             class="absolute right-full mr-1 bottom-0 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-20">
                                            <button @click="markNotInterested(lot); openDropdownId = null"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
                                                <span class="material-icons-outlined text-sm">visibility_off</span> Не интересно
                                            </button>
                                            <button @click="addToYougile(lot); openDropdownId = null"
                                                    :disabled="lot.on_board"
                                                    :class="lot.on_board ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-50'"
                                                    class="w-full text-left px-3 py-2 text-sm flex items-center gap-2">
                                                <span class="material-icons-outlined text-sm">dashboard</span> На доску
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-center gap-2 mt-8" x-show="totalPages > 1">
                    <button @click="loadLots(currentPage - 1)" :disabled="currentPage <= 1 || loadingPage"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <span x-show="!loadingPage">← Назад</span>
                        <span x-show="loadingPage" class="flex items-center"><svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                    </button>
                    <template x-for="(p, idx) in paginationRange()" :key="idx + '-' + totalPages">
                        <button @click="typeof p === 'number' && loadLots(p + 1)"
                                :disabled="loadingPage"
                                :class="{
                                    'bg-blue-600 text-white border-blue-600': (p + 1) === currentPage,
                                    'hover:bg-gray-50 border-gray-300': (p + 1) !== currentPage && typeof p === 'number',
                                    'border-transparent cursor-default text-gray-400': p === '...'
                                }"
                                class="px-3 py-1.5 text-sm border rounded-lg transition min-w-[36px] disabled:opacity-50 disabled:cursor-not-allowed"
                                x-text="typeof p === 'number' ? p + 1 : p"></button>
                    </template>
                    <button @click="loadLots(currentPage + 1)" :disabled="currentPage >= totalPages || loadingPage"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <span x-show="!loadingPage">Вперед →</span>
                        <span x-show="loadingPage" class="flex items-center"><svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                    </button>
                </div>
            </div>
        </template>
    </main>

    <!-- Lot Detail Modal -->
    <template x-if="selectedLot">
        <div class="fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4"
             @click.self="closeLotModal()">
            <div class="bg-white rounded-2xl w-full max-w-7xl max-h-[95vh] shadow-2xl flex flex-col"
                 @click.stop="if(!$event.target.closest('.relative')) openDropdown = null">
                <!-- Compact Header -->
                <div class="flex-shrink-0 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-sm font-semibold text-gray-800 truncate" x-text="selectedLot.cadastral_number || selectedLot.lot_name"></h2>
                        <a :href="'https://torgi.gov.ru/new/public/lots/lot/' + selectedLot.id" target="_blank" rel="noopener"
                           class="text-gray-400 hover:text-blue-500 transition shrink-0" title="Открыть на Торги">
                            <span class="material-icons-outlined text-sm">open_in_new</span>
                        </a>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <div class="relative">
                            <button @click.stop="openDropdown = openDropdown === 'header' ? null : 'header'" class="p-1.5 hover:bg-gray-100 rounded-lg">
                                <span class="material-icons-outlined text-gray-500 text-lg">more_vert</span>
                            </button>
                            <div x-show="openDropdown === 'header'" x-cloak
                                 class="absolute right-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-30">
                                <template x-if="!selectedLot.is_not_interested">
                                    <button @click="markNotInterested(selectedLot); closeLotModal(); openDropdown = null"
                                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <span class="material-icons-outlined text-sm">visibility_off</span> Не интересно
                                    </button>
                                </template>
                                <template x-if="selectedLot.is_not_interested">
                                    <button @click="restoreInterested(selectedLot)"
                                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <span class="material-icons-outlined text-sm">restore</span> Вернуть
                                    </button>
                                </template>
                                <button @click="addToYougile(selectedLot); openDropdown = null"
                                        :disabled="selectedLot.on_board"
                                        :class="selectedLot.on_board ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-50'"
                                        class="w-full text-left px-4 py-2.5 text-sm flex items-center gap-2">
                                    <span class="material-icons-outlined text-sm">dashboard</span> На доску
                                </button>
                            </div>
                        </div>
                        <button @click="closeLotModal()" class="p-1.5 hover:bg-gray-100 rounded-lg">
                            <span class="material-icons-outlined text-gray-500 text-lg">close</span>
                        </button>
                    </div>
                </div>

                <!-- Scrollable Content -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Slider + Info: side-by-side on desktop -->
                    <div class="flex flex-col md:flex-row">
                        <!-- Slider -->
                        <div class="md:w-1/2 bg-gray-100 shrink-0">
                            <template x-if="selectedLot.lot_images && selectedLot.lot_images.length > 0">
                                <div class="swiper lotSwiper h-[300px] md:h-[420px]">
                                    <div class="swiper-wrapper">
                                        <template x-for="(img, idx) in selectedLot.lot_images" :key="idx">
                                            <div class="swiper-slide">
                                                <img :src="'https://torgi.gov.ru/new/image-preview/v1/' + img + '?disposition=inline'"
                                                     class="w-full h-full object-contain bg-gray-100" loading="lazy">
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="selectedLot.lot_images.length > 1">
                                        <div>
                                            <div class="swiper-button-prev"></div>
                                            <div class="swiper-button-next"></div>
                                            <div class="swiper-pagination"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!selectedLot.lot_images || selectedLot.lot_images.length === 0">
                                <div class="h-[300px] md:h-[420px] flex items-center justify-center text-gray-300">
                                    <span class="material-icons-outlined text-5xl">image_not_supported</span>
                                </div>
                            </template>
                        </div>

                        <!-- Info -->
                        <div class="md:w-1/2 p-4 space-y-2">
                            <!-- Price (large) -->
                            <div class="text-xl font-bold text-green-700" x-text="formatPrice(selectedLot.price_min)"></div>
                            <div class="text-sm text-gray-600 !mt-0" x-text="selectedLot.lot_vat_name || ''"></div>

                            <div class="border-t border-gray-100 pt-2 space-y-1.5">
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px] shrink-0">Адрес:</span>
                                    <div class="flex items-center gap-1 flex-1 min-w-0">
                                        <span class="text-sm text-gray-700 flex-1 min-w-0 outline-none"
                                              contenteditable="true"
                                              x-text="selectedLot.custom_address || selectedLot.estate_address || '—'"
                                              @blur="saveCustomAddress($event.target.textContent)"
                                              @keydown.enter.prevent="$event.target.blur()"
                                              :title="selectedLot.custom_address ? 'Отредактировано вручную' : ''"></span>
                                        <button x-show="selectedLot.custom_address"
                                                @click="resetCustomAddress()"
                                                class="shrink-0 p-0.5 text-gray-400 hover:text-gray-600 transition"
                                                title="Вернуть оригинальный адрес">
                                            <span class="material-icons-outlined text-sm">restart_alt</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Площадь:</span>
                                    <span class="text-sm text-gray-700" x-text="formatArea(selectedLot.area)"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Использование:</span>
                                    <span class="text-sm text-gray-700" x-text="selectedLot.permitted_use_name || '—'"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Тип торгов:</span>
                                    <span class="text-sm text-gray-700" x-text="selectedLot.bidd_form_name || '—'"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">ЭТП:</span>
                                    <template x-if="selectedLot.etp_url">
                                        <a :href="selectedLot.etp_url" target="_blank" class="text-sm text-blue-600 hover:underline" x-text="etpNames[selectedLot.etp_code] || selectedLot.etp_code"></a>
                                    </template>
                                    <template x-if="!selectedLot.etp_url">
                                        <span class="text-sm text-gray-700" x-text="etpNames[selectedLot.etp_code] || selectedLot.etp_code || '—'"></span>
                                    </template>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Шаг торгов:</span>
                                    <span class="text-sm text-gray-700" x-text="selectedLot.price_step ? formatPrice(selectedLot.price_step) : '—'"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Задаток:</span>
                                    <span class="text-sm text-gray-700" x-text="selectedLot.deposit ? formatPrice(selectedLot.deposit) : '—'"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Подача заявок до:</span>
                                    <span class="text-sm text-gray-700" :class="{ 'countdown-expired': isExpired(selectedLot.bidd_end_time) }" x-text="selectedLot.bidd_end_time ? formatDate(selectedLot.bidd_end_time) : '—'"></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="text-sm text-gray-500 min-w-[130px]">Старт аукциона:</span>
                                    <span class="text-sm text-gray-700" x-text="selectedLot.auction_start_date ? formatDate(selectedLot.auction_start_date) : '—'"></span>
                                </div>
                                <div x-show="selectedLot.estate_address || (lotPolygon && lotPolygon.center_lat && lotPolygon.center_lon)" class="flex items-center gap-3">
                                    <a x-show="selectedLot.estate_address" :href="'https://www.avito.ru/all/zemelnye_uchastki?q=' + encodeURIComponent(selectedLot.custom_address || selectedLot.estate_address)"
                                       target="_blank" rel="noopener"
                                       class="text-gray-400 hover:text-gray-600 transition">
                                        <img src="{{ asset('Avito_logo.svg') }}" class="w-14 h-14" alt="Avito">
                                    </a>
                                    <a x-show="lotPolygon && lotPolygon.center_lat && lotPolygon.center_lon" :href="'https://www.cian.ru/map/?center=' + lotPolygon.center_lat + '%2C' + lotPolygon.center_lon + '&deal_type=sale&engine_version=2&object_type[0]=3&offer_type=suburban&zoom=15'"
                                       target="_blank" rel="noopener"
                                       class="text-gray-400 hover:text-gray-600 transition">
                                        <img src="{{ asset('cian.svg') }}" class="w-14 h-10 -mt-[5px]" alt="ЦИАН">
                                    </a>
                                    <a x-show="lotPolygon && lotPolygon.center_lat && lotPolygon.center_lon" :href="(() => { const lat = lotPolygon.center_lat, lon = lotPolygon.center_lon; const dlat = 0.005, dlon = 0.015; return 'https://domclick.ru/search/on-map?deal_type=sale&category=living&offer_type=lot&sw=' + (lat - dlat) + '%2C' + (lon - dlon) + '&ne=' + (lat + dlat) + '%2C' + (lon + dlon); })()"
                                       target="_blank" rel="noopener"
                                       class="text-gray-400 hover:text-gray-600 transition">
                                        <img src="{{ asset('domclick-logo.svg') }}" class="h-5 w-auto" alt="ДомКлик">
                                    </a>
                                    <div class="flex items-center gap-1 ml-2">
                                        <input type="text" inputmode="numeric"
                                               :value="selectedLot.market_price ? formatNumber(selectedLot.market_price) : ''"
                                               @input="formatMarketPriceInput($event)"
                                               @blur="saveMarketPrice($event.target.value)"
                                               class="w-32 text-sm text-gray-700 border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400"
                                               placeholder="Рын. цена">
                                        <span class="text-sm text-gray-500">руб.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents (compact list) -->
                    <template x-if="selectedLot.lot_attachments && selectedLot.lot_attachments.length > 0">
                        <div class="px-4 pb-3 pt-4">
                            <h3 class="text-xs font-semibold text-gray-500 mb-1">Документы лота</h3>
                            <div class="text-sm">
                                <template x-for="(doc, idx) in selectedLot.lot_attachments" :key="idx">
                                    <div class="flex items-center gap-2 py-0.5">
                                        <span class="material-icons-outlined text-xs text-gray-400 shrink-0">description</span>
                                        <button @click.stop="handleFileClick(doc)"
                                                class="text-sm truncate text-left text-blue-600 hover:underline transition"
                                                x-text="doc.fileName"></button>
                                        <a :href="'https://torgi.gov.ru/new/file-store/v1/' + doc.fileId"
                                           @click.stop
                                           class="text-gray-400 hover:text-gray-600 shrink-0 ml-1"
                                           title="Скачать оригинал">
                                            <span class="material-icons-outlined text-sm">download</span>
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="selectedLot.notice_attachments && selectedLot.notice_attachments.length > 0">
                        <div class="px-4 pb-3 pt-4">
                            <h3 class="text-xs font-semibold text-gray-500 mb-1">Документы извещения</h3>
                            <div class="text-sm">
                                <template x-for="(doc, idx) in selectedLot.notice_attachments" :key="idx">
                                    <div class="flex items-center gap-2 py-0.5">
                                        <span class="material-icons-outlined text-xs text-gray-400 shrink-0">description</span>
                                        <button @click.stop="handleFileClick(doc)"
                                                class="text-sm truncate text-left text-blue-600 hover:underline transition"
                                                x-text="doc.fileName"></button>
                                        <a :href="'https://torgi.gov.ru/new/file-store/v1/' + doc.fileId"
                                           @click.stop
                                           class="text-gray-400 hover:text-gray-600 shrink-0 ml-1"
                                           title="Скачать оригинал">
                                            <span class="material-icons-outlined text-sm">download</span>
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Maps Tabs -->
                    <div class="px-4 pb-4">
                        <div class="flex border-b border-gray-200 overflow-x-auto">
                            <template x-for="tab in ['google', 'terrain', 'yandex', 'nspd']" :key="tab">
                                <button @click="switchTab(tab)"
                                        :class="{ 'tab-active': activeTab === tab }"
                                        class="px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap transition">
                                    <span x-text="tabLabels[tab]"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="activeTab === 'terrain'" class="map-container border border-t-0 border-gray-200 rounded-b-xl relative">
                            <div id="terrain-map" class="w-full h-full"></div>
                        </div>

                        <div x-show="activeTab === 'google'" class="map-container border border-t-0 border-gray-200 rounded-b-xl">
                            <iframe x-show="lotPolygon && lotPolygon.center_lat && lotPolygon.center_lon"
                                    :src="'https://www.google.com/maps/embed/v1/place?key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8&q=' + lotPolygon.center_lat + ',' + lotPolygon.center_lon + '&zoom=18&maptype=satellite'"
                                    class="w-full h-full border-0" allowfullscreen loading="lazy"></iframe>
                            <div x-show="!lotPolygon || !lotPolygon.center_lat || !lotPolygon.center_lon" class="w-full h-full flex items-center justify-center text-gray-400">Координаты не определены</div>
                        </div>
                        <div x-show="activeTab === 'yandex'" class="map-container border border-t-0 border-gray-200 rounded-b-xl relative">
                            <div id="yandex-map" style="width:100%;height:100%;"></div>
                            <a x-show="lotPolygon && lotPolygon.center_lat && lotPolygon.center_lon"
                               :href="'https://yandex.ru/maps/?ll=' + lotPolygon.center_lon + '%2C' + lotPolygon.center_lat + '&z=18&l=sat%2Cskl'"
                               target="_blank" rel="noopener"
                               class="absolute top-2 left-2 z-10 inline-flex items-center gap-1.5 px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-black text-xs font-semibold rounded-lg shadow-sm transition-colors">
                                <span class="material-icons-outlined text-sm">open_in_new</span>
                                Открыть в Яндекс.Картах
                            </a>
                        </div>
                        <div x-show="activeTab === 'nspd'" class="map-container border border-t-0 border-gray-200 rounded-b-xl">
                            <iframe x-show="lotPolygon" :src="nspdIframeUrl" class="w-full h-full border-0" allowfullscreen loading="lazy"></iframe>
                            <div x-show="!lotPolygon" class="w-full h-full flex items-center justify-center text-gray-400">Полигон не загружен</div>
                        </div>
                    </div>

                    <!-- Comment Section -->
                    <div class="px-4 pb-5 pt-5 border-t border-gray-100">
                        <div class="flex items-start gap-2">
                            <span class="material-icons-outlined text-sm text-gray-400 mt-0.5">comment</span>
                            <span class="text-sm text-gray-700 flex-1 min-w-0 outline-none whitespace-pre-wrap min-h-[1.25rem]"
                                  contenteditable="true"
                                  x-text="selectedLot.comment || ''"
                                  @blur="saveComment($event.target.textContent)"
                                  @keydown.enter.prevent="if(!$event.shiftKey) $event.target.blur()"
                                  :data-placeholder="!selectedLot.comment ? 'Добавить комментарий...' : ''"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Doc Preview Modal -->
    <div x-show="previewDoc" x-cloak class="fixed inset-0 z-[60] modal-backdrop flex items-center justify-center p-4" @click.self="closeDocPreview()">
            <div class="bg-white rounded-2xl w-full max-w-7xl min-h-[85vh] max-h-[95vh] overflow-hidden shadow-2xl flex flex-col">
                <div class="flex items-center justify-between px-6 py-3 border-b shrink-0">
                    <h3 class="font-semibold text-gray-800 text-sm truncate" x-text="previewDoc ? previewDoc.fileName : ''"></h3>
                    <button @click="closeDocPreview()" class="p-1.5 hover:bg-gray-100 rounded-lg shrink-0">
                        <span class="material-icons-outlined text-lg">close</span>
                    </button>
                </div>
                <div class="flex-1 bg-gray-100 flex items-center justify-center overflow-auto">
                    <iframe x-show="previewDoc"
                            :src="previewDoc ? getDocPreviewUrl(previewDoc) : ''"
                            class="w-full h-full border-0 min-h-[80vh]"
                            loading="lazy"></iframe>
                </div>
            </div>
        </div>

    <!-- Image Preview Modal -->
    <div x-show="previewImageUrl" x-cloak class="fixed inset-0 z-[60] modal-backdrop flex items-center justify-center p-4" @click.self="closeImagePreview()">
        <div class="bg-white rounded-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="flex items-center justify-between px-6 py-3 border-b shrink-0">
                <h3 class="font-semibold text-gray-800 text-sm truncate" x-text="previewImageName"></h3>
                <button @click="closeImagePreview()" class="p-1.5 hover:bg-gray-100 rounded-lg shrink-0">
                    <span class="material-icons-outlined text-lg">close</span>
                </button>
            </div>
            <div class="flex-1 bg-gray-100 flex items-center justify-center overflow-auto p-4">
                <img :src="previewImageUrl" :alt="previewImageName"
                     class="max-w-full max-h-full object-contain" loading="lazy">
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function lotApp() {
    return {
        lots: [],
        etpNames: {},
        totalElements: 0,
        totalPages: 0,
        currentPage: 0,
        loading: false,
        loadingPage: false,
        _mdX: 0,
        _mdY: 0,
        error: null,
        sidebarOpen: false,
        selectedLot: null,
        openDropdown: null,
        activeTab: 'google',
        lotPolygon: null,
        previewDoc: null,
        previewImageUrl: null,
        previewImageName: '',
        openDropdownId: null,
        countdownIntervals: {},
        terrainMap: null,
        yandexMap: null,
        yandexApiKey: '{{ $yandexApiKey }}',

        get nspdIframeUrl() {
            if (!this.lotPolygon || !this.lotPolygon.mercator_x || !this.lotPolygon.mercator_y) return '';
            const mx = this.lotPolygon.mercator_x;
            const my = this.lotPolygon.mercator_y;
            return 'https://nspd.gov.ru/cadastral-price/search?zoom=18&coordinate_x=' + mx + '&coordinate_y=' + my + '&baseLayerId=36344';
        },
        mapsInitialized: false,

        tabLabels: {
            terrain: 'Рельеф 3D',
            google: 'Google Maps',
            yandex: 'Яндекс.Карты',
            nspd: 'НСПД'
        },

        filters: {
            price_min_from: 1,
            price_min_to: 2300000,
            pub_from: new Date().toISOString().split('T')[0],
            pub_to: new Date().toISOString().split('T')[0],
        },

        today: new Date().toISOString().split('T')[0],

        now: Date.now(),

        init() {
            this.loadLots(1);
            setInterval(() => { this.now = Date.now(); }, 1000);
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },

        async loadLots(page = 1) {
            this.loadingPage = true;
            this.loading = this.lots.length === 0;
            this.error = null;
            this.sidebarOpen = false;
            try {
                const params = new URLSearchParams({
                    page: page - 1,
                    price_min_from: this.filters.price_min_from,
                    price_min_to: this.filters.price_min_to,
                    pub_from: this.filters.pub_from,
                    pub_to: this.filters.pub_to,
                });
                const response = await fetch(`/api/lots/fetch?${params}`);
                const data = await response.json();

                if (data.error) {
                    this.error = data.error;
                } else {
                    this.lots = data.lots || [];
                    this.totalPages = data.total_pages || 0;
                    this.totalElements = data.total_elements || 0;
                    this.currentPage = (data.current_page || 0) + 1;
                    if (data.etps) {
                        this.etpNames = data.etps;
                    }
                }
            } catch (e) {
                this.error = 'Ошибка подключения к серверу';
                console.error(e);
            }
            this.loading = false;
            this.loadingPage = false;
        },

        async openLotModal(lot) {
            document.body.style.overflow = 'hidden';
            this.selectedLot = { ...lot };
            this.activeTab = 'google';
            this.lotPolygon = null;
            this.mapsInitialized = false;

            try {
                const res = await fetch(`/api/lots/${lot.id}/detail`);
                const data = await res.json();
                if (data.lot) {
                    Object.assign(this.selectedLot, data.lot);
                }
                if (data.detail) {
                    Object.assign(this.selectedLot, this.parseDetailData(data.detail));
                }
                if (data.polygon) {
                    this.lotPolygon = data.polygon;
                }
                if (data.etp) {
                    this.etpNames[lot.etp_code] = data.etp.short_name;
                }
                const idx = this.lots.findIndex(l => l.id === lot.id);
                if (idx !== -1) {
                    this.lots[idx].is_viewed = true;
                }
                this.selectedLot.is_viewed = true;
            } catch (e) {
                console.error('Failed to load lot detail', e);
            }

            await this.$nextTick();
            this.initMaps();
            this.initSwiper();
        },

        closeLotModal() {
            document.body.style.overflow = '';
            this.selectedLot = null;
            this.lotPolygon = null;
            this.openDropdown = null;
            if (this.terrainMap) {
                try { this.terrainMap.remove(); } catch (e) {}
                this.terrainMap = null;
            }
            if (this.yandexMap) {
                try { this.yandexMap.remove(); } catch (e) {}
                this.yandexMap = null;
            }
            const terrainEl = document.getElementById('terrain-map');
            const yandexEl = document.getElementById('yandex-map');
            if (terrainEl) terrainEl._mapInit = false;
            if (yandexEl) yandexEl._mapInit = false;
        },

        parseDetailData(detail) {
            const data = {};
            data.price_step = detail.priceStep;
            data.deposit = detail.deposit;
            data.etp_url = detail.etpUrl;
            data.estate_address = detail.estateAddress;
            data.auction_start_date = detail.auctionStartDate;
            data.bidd_start_time = detail.biddStartTime;
            data.bidd_end_time = detail.biddEndTime;
            data.version_id = detail.versionId;
            if (detail.point) {
                data.lat = detail.point.lat;
                data.lon = detail.point.lon;
            }
            if (detail.lotAttachments) {
                data.lot_attachments = detail.lotAttachments;
            }
            if (detail.noticeAttachments) {
                data.notice_attachments = detail.noticeAttachments;
            }
            return data;
        },

        initSwiper() {
            setTimeout(() => {
                const el = document.querySelector('.lotSwiper');
                if (el && el.swiper) {
                    el.swiper.destroy(true, true);
                }
                new Swiper('.lotSwiper', {
                    lazy: true,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                });
            }, 100);
        },

        async switchTab(tab) {
            this.activeTab = tab;
            await this.$nextTick();
            if (this.selectedLot) {
                const lat = this.selectedLot.lat;
                const lon = this.selectedLot.lon;
                if (!lat || !lon) return;
                const center = this.lotPolygon ? this.calculatePolygonCenter(this.lotPolygon.coordinates) : [lon, lat];

                if (tab === 'terrain' && this.terrainMap) {
                    this.terrainMap.resize();
                    this.terrainMap.flyTo({ center: center, zoom: 17, pitch: 70, duration: 800 });
                } else if (tab === 'yandex' && this.yandexMap) {
                    this.yandexMap.resize();
                    this.yandexMap.flyTo({ center: center, zoom: 17, duration: 800 });
                } else {
                    this.initMaps();
                }
            }
        },

        initMaps() {
            if (!this.selectedLot) return;
            const lat = this.selectedLot.lat;
            const lon = this.selectedLot.lon;
            if (!lat || !lon) return;

            const center = this.lotPolygon ? this.calculatePolygonCenter(this.lotPolygon.coordinates) : [lon, lat];

            if (this.activeTab === 'terrain' && !this.terrainMap) {
                this.initTerrainMap(center, this.lotPolygon);
            } else if (this.activeTab === 'yandex' && !this.yandexMap) {
                this.initYandexMap(center, this.lotPolygon);
            }
        },

        async initTerrainMap(center, polygon) {
            const container = document.getElementById('terrain-map');
            if (!container || container._mapInit) return;
            container._mapInit = true;

            try {
                if (!window.maplibregl) {
                    await this.loadMapLibre();
                }
                const maplibregl = window.maplibregl;

                this.terrainMap = new maplibregl.Map({
                    container: 'terrain-map',
                    center: center,
                    zoom: 15,
                    pitch: 70,
                    maxPitch: 85,
                    style: {
                        version: 8,
                        sources: {
                            osm: {
                                type: 'raster',
                                tiles: ['https://a.tile.openstreetmap.org/{z}/{x}/{y}.png'],
                                tileSize: 256,
                                attribution: '&copy; OpenStreetMap Contributors',
                                maxzoom: 19
                            },
                            terrainSource: {
                                type: 'raster-dem',
                                url: 'https://tiles.mapterhorn.com/tilejson.json'
                            },
                            hillshadeSource: {
                                type: 'raster-dem',
                                url: 'https://tiles.mapterhorn.com/tilejson.json'
                            }
                        },
                        layers: [
                            {
                                id: 'osm',
                                type: 'raster',
                                source: 'osm'
                            },
                            {
                                id: 'hills',
                                type: 'hillshade',
                                source: 'hillshadeSource',
                                layout: {visibility: 'visible'},
                                paint: {'hillshade-shadow-color': '#473B24'}
                            }
                        ],
                        terrain: {
                            source: 'terrainSource',
                            exaggeration: 1
                        },
                        sky: {}
                    },
                    maxZoom: 18,
                });

                this.terrainMap.addControl(
                    new maplibregl.NavigationControl({
                        visualizePitch: true,
                        showZoom: true,
                        showCompass: true
                    }),
                    'top-right'
                );

                this.terrainMap.addControl(
                    new maplibregl.TerrainControl({
                        source: 'terrainSource',
                        exaggeration: 1
                    }),
                    'top-right'
                );
                this.terrainMap.addControl(new maplibregl.FullscreenControl(), 'top-right');

                this.terrainMap.on('load', () => {
                    this.terrainMap.addSource('contours', {
                        type: 'vector',
                        url: 'https://api.maptiler.com/tiles/contours/tiles.json?key=get_your_own_OpIi9ZULNHzrESv6T2vL',
                    });
                    this.terrainMap.addLayer({
                        id: 'contour-lines',
                        type: 'line',
                        source: 'contours',
                        'source-layer': 'contour',
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: { 'line-color': '#ff69b4', 'line-width': 1 },
                    });
                    this.terrainMap.addLayer({
                        id: 'contour-labels',
                        type: 'symbol',
                        source: 'contours',
                        'source-layer': 'contour',
                        layout: {
                            'text-field': '{height}',
                            'symbol-placement': 'line',
                            'text-font': ['Noto Sans Regular'],
                        },
                        paint: { 'text-color': '#ff69b4' },
                    });

                    if (polygon && polygon.coordinates) {
                        this.drawPolygon(this.terrainMap, polygon.coordinates, 'terrain-polygon');
                    }
                });

                this.terrainMap.on('error', (e) => {
                    console.warn('Terrain map error:', e.error?.message || e.message);
                });
            } catch (e) {
                console.warn('Failed to init terrain map:', e.message);
                container._mapInit = false;
            }
        },



        async initYandexMap(center, polygon) {
            const container = document.getElementById('yandex-map');
            if (!container || container._mapInit) return;
            container._mapInit = true;

            try {
                if (!window.maplibregl) {
                    await this.loadMapLibre();
                }
                const maplibregl = window.maplibregl;

                this.yandexMap = new maplibregl.Map({
                    container: 'yandex-map',
                    center: center,
                    zoom: 17,
                    style: {
                        version: 8,
                        sources: {
                            'yandex-tiles': {
                                type: 'raster',
                                tiles: ['https://tiles.api-maps.yandex.ru/v1/tiles/?x={x}&y={y}&z={z}&lang=ru_RU&l=map&projection=web_mercator&scale=2&apikey=' + this.yandexApiKey],
                                tileSize: 512,
                                attribution: '© <a href="https://yandex.ru/dev/maps/">Яндекс</a>',
                                maxzoom: 20,
                            },
                        },
                        layers: [{
                            id: 'yandex-tiles-layer',
                            type: 'raster',
                            source: 'yandex-tiles',
                            minzoom: 0,
                            maxzoom: 19,
                        }],
                    },
                });

                this.yandexMap.addControl(new maplibregl.NavigationControl(), 'top-right');
                this.yandexMap.addControl(new maplibregl.FullscreenControl(), 'top-right');

                this.yandexMap.on('load', () => {
                    if (polygon && polygon.coordinates) {
                        this.drawPolygon(this.yandexMap, polygon.coordinates, 'yandex-polygon');
                    }
                    new maplibregl.Marker({ color: '#ef4444' }).setLngLat(center).addTo(this.yandexMap);
                });

                this.yandexMap.on('error', (e) => {
                    console.warn('Yandex map error:', e.error?.message || e.message);
                });
            } catch (e) {
                console.warn('Failed to init yandex map:', e.message);
                container._mapInit = false;
            }
        },

        drawPolygon(map, coordinates, layerId) {
            const geojson = {
                type: 'Feature',
                geometry: { type: 'Polygon', coordinates: [coordinates] },
                properties: {},
            };

            map.addSource(layerId, { type: 'geojson', data: geojson });
            map.addLayer({
                id: layerId + '-fill',
                type: 'fill',
                source: layerId,
                paint: { 'fill-color': '#3b82f6', 'fill-opacity': 0.2 },
            });
            map.addLayer({
                id: layerId + '-line',
                type: 'line',
                source: layerId,
                paint: { 'line-color': '#3b82f6', 'line-width': 2 },
            });

            const bounds = new window.maplibregl.LngLatBounds();
            coordinates.forEach(c => bounds.extend(c));
            map.fitBounds(bounds, { padding: 50 });
        },

        calculatePolygonCenter(coordinates) {
            if (!coordinates || coordinates.length === 0) return [0, 0];
            let sumLon = 0, sumLat = 0;
            coordinates.forEach(c => { sumLon += c[0]; sumLat += c[1]; });
            return [sumLon / coordinates.length, sumLat / coordinates.length];
        },

        async loadMapLibre() {
            if (window.maplibregl) return;
            return new Promise((resolve) => {
                const check = () => {
                    if (window.maplibregl) { resolve(); return; }
                    setTimeout(check, 50);
                };
                window.addEventListener('maplibre-loaded', resolve, { once: true });
                check();
            });
        },

        async markNotInterested(lot) {
            try {
                await fetch('/api/lots/not-interested', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({
                        id: lot.id,
                        center_lat: this.lotPolygon?.center_lat ?? null,
                        center_lon: this.lotPolygon?.center_lon ?? null,
                        mercator_x: this.lotPolygon?.mercator_x ?? null,
                        mercator_y: this.lotPolygon?.mercator_y ?? null,
                    }),
                });
                const idx = this.lots.findIndex(l => l.id === lot.id);
                if (idx !== -1) this.lots[idx].is_not_interested = true;
                if (this.selectedLot && this.selectedLot.id === lot.id) this.selectedLot.is_not_interested = true;
            } catch (e) { console.error(e); }
        },

        async restoreInterested(lot) {
            try {
                await fetch('/api/lots/restore-interested', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ id: lot.id }),
                });
                const idx = this.lots.findIndex(l => l.id === lot.id);
                if (idx !== -1) this.lots[idx].is_not_interested = false;
                if (this.selectedLot && this.selectedLot.id === lot.id) this.selectedLot.is_not_interested = false;
                this.closeLotModal();
                this.openDropdown = null;
            } catch (e) { console.error(e); }
        },

        async addToYougile(lot) {
            try {
                const res = await fetch('/api/lots/add-to-yougile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({
                        id: lot.id,
                        center_lat: this.lotPolygon?.center_lat ?? null,
                        center_lon: this.lotPolygon?.center_lon ?? null,
                        mercator_x: this.lotPolygon?.mercator_x ?? null,
                        mercator_y: this.lotPolygon?.mercator_y ?? null,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    const idx = this.lots.findIndex(l => l.id === lot.id);
                    if (idx !== -1) this.lots[idx].on_board = true;
                    if (this.selectedLot && this.selectedLot.id === lot.id) this.selectedLot.on_board = true;
                } else if (data.error) {
                    alert(data.error);
                }
            } catch (e) { console.error(e); alert('Ошибка подключения'); }
        },

        openDocPreview(doc) {
            this.previewDoc = doc;
        },

        closeDocPreview() {
            this.previewDoc = null;
        },



        closeImagePreview() {
            this.previewImageUrl = null;
            this.previewImageName = '';
        },

        async saveComment(value) {
            const trimmed = (value || '').trim() || null;
            if ((this.selectedLot.comment || null) === trimmed) return;
            try {
                const res = await fetch(`/api/lots/${this.selectedLot.id}/comment`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ comment: trimmed })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedLot.comment = data.comment;
                    const lotInList = this.lots.find(l => l.id === this.selectedLot.id);
                    if (lotInList) lotInList.comment = data.comment;
                }
            } catch (e) {
                console.error(e);
            }
        },

        async saveMarketPrice(value) {
            const cleaned = (value || '').replace(/[^\d]/g, '');
            const price = cleaned ? parseFloat(cleaned) : null;
            if (this.selectedLot.market_price === price) return;
            try {
                const res = await fetch(`/api/lots/${this.selectedLot.id}/market-price`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ market_price: price })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedLot.market_price = price;
                    const lotInList = this.lots.find(l => l.id === this.selectedLot.id);
                    if (lotInList) lotInList.market_price = price;
                }
            } catch (e) {
                console.error(e);
            }
        },

        async saveCustomAddress(value) {
            const addr = value && value.trim() ? value.trim() : null;
            if ((this.selectedLot.custom_address || null) === addr) return;
            try {
                const res = await fetch(`/api/lots/${this.selectedLot.id}/custom-address`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ custom_address: addr })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedLot.custom_address = addr;
                    const lotInList = this.lots.find(l => l.id === this.selectedLot.id);
                    if (lotInList) lotInList.custom_address = addr;
                }
            } catch (e) {
                console.error(e);
            }
        },

        resetCustomAddress() {
            this.saveCustomAddress(null);
        },

        pluralize(n, one, few, many) {
            const abs = Math.abs(n) % 100;
            const lastDigit = abs % 10;
            if (abs > 10 && abs < 20) return many;
            if (lastDigit > 1 && lastDigit < 5) return few;
            if (lastDigit === 1) return one;
            return many;
        },

        getDocPreviewUrl(doc) {
            return `/api/preview-file?file_id=${doc.fileId}&file_name=${encodeURIComponent(doc.fileName)}`;
        },

        getDownloadUrl(doc) {
            return `/api/download-file?file_id=${doc.fileId}&file_name=${encodeURIComponent(doc.fileName)}`;
        },

        toggleDropdown(lotId) {
            this.openDropdownId = this.openDropdownId === lotId ? null : lotId;
        },

        _getFileExt(doc) {
            return (doc.fileName || '').split('.').pop().toLowerCase();
        },

        _isImageFile(doc) {
            return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'].includes(this._getFileExt(doc));
        },

        _isArchiveFile(doc) {
            return ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz'].includes(this._getFileExt(doc));
        },

        _getDirectTorgiUrl(doc) {
            return 'https://torgi.gov.ru/new/file-store/v1/' + doc.fileId;
        },

        handleFileClick(doc) {
            if (this._isArchiveFile(doc)) {
                window.location.href = this._getDirectTorgiUrl(doc);
            } else if (this._isImageFile(doc)) {
                window.open('https://torgi.gov.ru/new/image-preview/v1/' + doc.fileId + '?disposition=inline', '_blank');
            } else {
                this.openDocPreview(doc);
            }
        },

        formatNumber(num) {
            return new Intl.NumberFormat('ru-RU').format(num);
        },

        formatMarketPriceInput(e) {
            const raw = e.target.value.replace(/[^\d]/g, '');
            if (raw) {
                e.target.value = this.formatNumber(parseInt(raw, 10));
            } else {
                e.target.value = '';
            }
        },

        formatPrice(price) {
            if (!price && price !== 0) return '—';
            return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(price);
        },

        formatArea(area) {
            if (!area && area !== 0) return '—';
            return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(area) + ' м²';
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        isExpired(dateStr) {
            if (!dateStr) return false;
            void this.now;
            return new Date(dateStr) < new Date();
        },

        getCountdown(dateStr) {
            if (!dateStr) return '';
            void this.now;
            const end = new Date(dateStr);
            const now = new Date();
            const diff = end - now;
            if (diff <= 0) return 'Истёк';

            const days = Math.floor(diff / 86400000);
            const hours = Math.floor((diff % 86400000) / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);

            const parts = [];
            if (days > 0) parts.push(days + 'д');
            if (hours > 0) parts.push(hours + 'ч');
            if (minutes > 0) parts.push(minutes + 'м');
            parts.push(seconds + 'с');
            return parts.join(' ');
        },

        paginationRange() {
            const range = [];
            const total = this.totalPages;
            const current = this.currentPage - 1;
            const delta = 2;

            for (let i = 0; i < total; i++) {
                if (i === 0 || i === total - 1 || (i >= current - delta && i <= current + delta)) {
                    range.push(i);
                } else if (range[range.length - 1] !== '...') {
                    range.push('...');
                }
            }
            return range;
        },
    };
}
</script>
@endpush
