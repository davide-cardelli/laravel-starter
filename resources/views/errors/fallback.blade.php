<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} &mdash; Error</title>
    <style>
        html { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: ui-sans-serif, system-ui, sans-serif;
            text-align: center;
            padding: 1.5rem;
            background: Canvas;
            color: CanvasText;
        }
        .code { font-size: 4rem; font-weight: 700; opacity: 0.5; }
        h1 { font-size: 1.5rem; margin: 0; }
        p { opacity: 0.7; max-width: 28rem; }
    </style>
</head>
<body>
    <p class="code">{{ $status }}</p>
    <h1>Something went wrong</h1>
    <p>The application could not complete your request. Please try again later.</p>
</body>
</html>
