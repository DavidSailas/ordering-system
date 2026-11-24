<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Poster · Cecilian Bites</title>
    <x-favicon />
    <style>
        :root { --brand: #e25806; --ink: #2b1409; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: var(--ink); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .bar { display: flex; justify-content: center; gap: 10px; padding: 16px; }
        .bar button, .bar a { padding: 11px 20px; border: 0; border-radius: 99px; background: var(--brand); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .bar a { background: #fff; color: var(--ink); border: 1px solid #d9dce3; }
        .sheet { width: 210mm; max-width: 100%; margin: 0 auto 32px; padding: 14mm 12mm; background: #fff; text-align: center; box-shadow: 0 6px 30px rgba(20, 24, 40, .12); }
        .brand { display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 26px; font-weight: 800; }
        .brand img { height: 52px; width: auto; }
        h1 { margin: 14px 0 6px; font-size: 44px; line-height: 1.1; color: var(--brand); }
        .lead { margin: 0 auto; max-width: 520px; font-size: 20px; color: #4b5563; }
        .qr { width: 96mm; max-width: 100%; margin: 14mm auto 8mm; padding: 6mm; border: 3px solid var(--brand); border-radius: 8mm; }
        .qr svg { display: block; width: 100%; height: auto; }
        .steps { display: flex; justify-content: center; gap: 22px; margin: 6mm auto; max-width: 560px; font-size: 18px; text-align: left; }
        .steps div { flex: 1; }
        .steps b { display: inline-grid; place-items: center; width: 30px; height: 30px; margin-bottom: 4px; border-radius: 50%; background: var(--brand); color: #fff; }
        .code { margin-top: 8mm; padding-top: 6mm; border-top: 2px dashed #d9dce3; font-size: 18px; color: #4b5563; }
        .code strong { display: block; margin-top: 4px; color: var(--ink); font-size: 46px; letter-spacing: .3em; }
        .foot { margin-top: 8mm; color: #6b7385; font-size: 14px; }
        @media print { body { background: #fff; } .bar { display: none; } .sheet { width: auto; margin: 0; box-shadow: none; padding: 0; } @page { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="bar"><button type="button" onclick="window.print()">Print this poster</button><a href="{{ route('admin.order-security.index') }}">Back</a></div>
    <div class="sheet">
        <div class="brand"><x-logo :size="52" /> Cecilian Bites</div>
        <h1>Scan to order</h1>
        <p class="lead">Order your food and drinks from your phone and we bring it to your room.</p>
        <div class="qr" id="qr"></div>
        <div class="steps">
            <div><b>1</b><br>Open your phone camera and point it at the code</div>
            <div><b>2</b><br>Tap the link that shows up</div>
            <div><b>3</b><br>Pick your food and type your room</div>
        </div>
        <div class="code">Can't scan? Open the website and type this code<strong>{{ $code }}</strong></div>
        <div class="foot">For our students, teachers and staff.</div>
    </div>
    <script src="{{ asset('vendor/qrcode.js') }}"></script>
    <script>
        var qr = qrcode(0, 'M'); qr.addData(@json($link)); qr.make();
        document.getElementById('qr').innerHTML = qr.createSvgTag({ scalable: true, margin: 0 });
    </script>
</body>
</html>
