<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ Str::beforeLast($pdfFilename, '.pdf') }}</title>
    <link rel="stylesheet" href="{{ asset('css/transcript-document.css') }}">
    <style>
        @page transcript {
            @bottom-left {
                content: {!! $pageIdentity !!};
            }
        }
    </style>
    <script src="{{ asset('js/transcript-print.js') }}" defer></script>
</head>
<body class="transcript-print-page">
    <aside class="transcript-controls">
        <button type="button" id="transcript-print" disabled>Print / Save PDF</button>
        <p>Choose Save as PDF and turn off browser headers and footers.</p>
        <noscript><p>Use your browser’s Print command to save the transcript.</p></noscript>
    </aside>
    @include('filament.official-transcripts.view', ['printPage' => true])
</body>
</html>
