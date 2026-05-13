<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Batch Renamer</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full font-sans antialiased text-gray-900">

<div class="min-h-full" x-data="pdfApp()">
    {{-- Header --}}
    <header class="bg-indigo-700 shadow">
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h1 class="text-2xl font-bold text-white">PDF Batch Renamer</h1>
            </div>
            <span class="text-indigo-200 text-sm">Laravel · Queue-powered · AI-enhanced</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

        {{-- Flash messages --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition
                 class="rounded-md bg-green-50 border border-green-200 p-4 flex items-start gap-3">
                <svg class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm text-green-800">{{ session('success') }}</p>
                <button @click="show = false" class="ml-auto text-green-500 hover:text-green-700">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 p-4">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm text-red-700">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Upload Zone --}}
        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Upload PDFs</h2>

            <form action="{{ route('pdf.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <div
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="handleDrop($event)"
                    :class="dragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50'"
                    class="border-2 border-dashed rounded-xl p-10 text-center transition-colors cursor-pointer"
                    @click="$refs.fileInput.click()"
                >
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold text-indigo-600">Click to browse</span> or drag &amp; drop PDFs here
                    </p>
                    <p class="text-xs text-gray-400 mt-1">PDF files only · max 50 MB each · multiple files supported</p>
                    <input
                        x-ref="fileInput"
                        type="file"
                        name="pdfs[]"
                        multiple
                        accept="application/pdf,.pdf"
                        class="hidden"
                        @change="handleFileSelect($event)"
                    >
                </div>

                {{-- File preview list --}}
                <template x-if="selectedFiles.length > 0">
                    <div class="mt-4 space-y-2">
                        <p class="text-sm font-medium text-gray-700">
                            <span x-text="selectedFiles.length"></span> file(s) selected:
                        </p>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden max-h-40 overflow-y-auto">
                            <template x-for="(file, idx) in selectedFiles" :key="idx">
                                <li class="flex items-center justify-between px-3 py-2 bg-white text-sm">
                                    <span class="text-gray-700 truncate" x-text="file.name"></span>
                                    <span class="text-gray-400 ml-2 flex-shrink-0" x-text="formatBytes(file.size)"></span>
                                </li>
                            </template>
                        </ul>
                        <button type="submit"
                                class="mt-2 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Upload &amp; Queue
                        </button>
                    </div>
                </template>
            </form>
        </section>

        {{-- Batch Actions --}}
        @if ($logs->isNotEmpty())
            <div class="flex flex-wrap gap-3 items-center">
                <form action="{{ route('pdf.process') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Process All
                    </button>
                </form>

                @if ($logs->where('status', 'completed')->whereNotNull('renamed_path')->isNotEmpty())
                    <a href="{{ route('pdf.download') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download ZIP
                    </a>
                @endif

                <span class="text-sm text-gray-500 ml-auto">
                    {{ $logs->count() }} file(s) ·
                    {{ $logs->where('status', 'completed')->count() }} completed ·
                    {{ $logs->where('status', 'processing')->count() }} processing ·
                    {{ $logs->where('status', 'failed')->count() }} failed
                </span>
            </div>
        @endif

        {{-- Table --}}
        @if ($logs->isNotEmpty())
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">#</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Original File</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Detected Title</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Year</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Renamed File</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-gray-50 transition"
                                    x-data="{ editing: false }">
                                    <td class="px-4 py-3 text-gray-500">{{ $log->id }}</td>

                                    <td class="px-4 py-3 text-gray-800 max-w-xs">
                                        <span class="truncate block" title="{{ $log->original_name }}">
                                            {{ $log->original_name }}
                                        </span>
                                    </td>

                                    {{-- Title / Year columns with inline editing --}}
                                    <td class="px-4 py-3 max-w-xs">
                                        <span x-show="!editing" class="text-gray-700 truncate block"
                                              title="{{ $log->detected_title }}">
                                            {{ $log->detected_title ?? '—' }}
                                        </span>
                                        <form x-show="editing" x-cloak
                                              action="{{ route('pdf.update', $log) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="text" name="detected_title"
                                                   value="{{ $log->detected_title }}"
                                                   class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                                            <input type="hidden" name="detected_year" value="{{ $log->detected_year }}">
                                            <input type="hidden" name="clean_title" value="{{ $log->clean_title }}">
                                        </form>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span x-show="!editing" class="text-gray-700">{{ $log->detected_year ?? '—' }}</span>
                                        <form x-show="editing" x-cloak
                                              action="{{ route('pdf.update', $log) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="detected_title" value="{{ $log->detected_title }}">
                                            <input type="text" name="detected_year"
                                                   value="{{ $log->detected_year }}"
                                                   maxlength="4"
                                                   class="w-20 rounded border border-gray-300 px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                                            <input type="hidden" name="clean_title" value="{{ $log->clean_title }}">
                                        </form>
                                    </td>

                                    <td class="px-4 py-3 max-w-xs">
                                        @if ($log->renamed_path)
                                            <span class="text-indigo-600 font-medium truncate block"
                                                  title="{{ $log->renamed_filename }}">
                                                {{ $log->renamed_filename }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @php
                                            $badge = match($log->status) {
                                                'pending'    => 'bg-yellow-100 text-yellow-700',
                                                'processing' => 'bg-blue-100 text-blue-700',
                                                'completed'  => 'bg-green-100 text-green-700',
                                                'failed'     => 'bg-red-100 text-red-700',
                                                default      => 'bg-gray-100 text-gray-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                        @if ($log->status === 'failed' && $log->error_message)
                                            <p class="text-xs text-red-500 mt-1 truncate max-w-[180px]"
                                               title="{{ $log->error_message }}">
                                                {{ $log->error_message }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            {{-- Edit / Save toggle --}}
                                            <button @click="editing = !editing"
                                                    class="rounded px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition">
                                                <span x-text="editing ? 'Cancel' : 'Edit'"></span>
                                            </button>

                                            {{-- Submit edit form (only visible when editing) --}}
                                            <button x-show="editing" x-cloak
                                                    @click="$el.closest('tr').querySelector('form').submit()"
                                                    class="rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-500 transition">
                                                Save
                                            </button>

                                            {{-- Delete --}}
                                            <form action="{{ route('pdf.destroy', $log) }}" method="POST"
                                                  onsubmit="return confirm('Delete this entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 transition">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-dashed border-gray-300 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="mt-4 text-gray-400 text-sm">No PDFs uploaded yet. Upload some files to get started.</p>
            </div>
        @endif

    </main>
</div>

<script>
function pdfApp() {
    return {
        dragging: false,
        selectedFiles: [],

        handleDrop(event) {
            this.dragging = false;
            const dt = event.dataTransfer;
            const files = Array.from(dt.files).filter(f => f.type === 'application/pdf');
            this.addFiles(files);
            this.syncInput(files);
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.addFiles(files);
        },

        addFiles(files) {
            this.selectedFiles = [...this.selectedFiles, ...files];
        },

        syncInput(files) {
            const input = this.$refs.fileInput;
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            input.files = dt.files;
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },
    };
}
</script>

</body>
</html>
