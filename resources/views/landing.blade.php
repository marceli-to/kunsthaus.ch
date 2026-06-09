<!DOCTYPE html>
<html lang="de" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ja zum Kunstmuseum</title>
    <meta name="description" content="Unterstütze das Kunstmuseum — gestalte dein persönliches «Ja zum Kunstmuseum» Bild.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">

    <header class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
        <span class="text-sm font-semibold tracking-[0.2em] uppercase text-ink/70">Kunstmuseum</span>
        <a href="#mitmachen"
           class="rounded-full bg-ink px-5 py-2 text-sm font-medium text-canvas transition hover:bg-clay">
            Jetzt mitmachen
        </a>
    </header>

    <main>
        {{-- Hero --}}
        <section class="mx-auto max-w-5xl px-6 pt-12 pb-20 text-center sm:pt-20">
            <p class="mb-5 text-sm font-medium tracking-[0.2em] uppercase text-clay">Eine Abstimmung fürs Museum</p>
            <h1 class="font-serif text-5xl leading-[1.05] font-semibold text-balance sm:text-7xl">
                Ja zum <span class="italic text-clay">Kunstmuseum</span>
            </h1>
            <p class="mx-auto mt-6 max-w-xl text-lg text-pretty text-ink/70">
                Kunst gehört allen. Setz ein Zeichen für ein offenes, lebendiges Museum —
                und gestalte dein ganz persönliches «Ja» als Plakatbild.
            </p>
            <div class="mt-9 flex items-center justify-center gap-3">
                <a href="#mitmachen"
                   class="rounded-full bg-clay px-7 py-3 font-medium text-canvas shadow-sm transition hover:bg-ink">
                    Dein Bild gestalten
                </a>
                <a href="#warum" class="rounded-full px-7 py-3 font-medium text-ink/70 transition hover:text-ink">
                    Mehr erfahren
                </a>
            </div>
        </section>

        {{-- Why --}}
        <section id="warum" class="border-y border-ink/10 bg-sand/40">
            <div class="mx-auto grid max-w-5xl gap-10 px-6 py-16 sm:grid-cols-3">
                <div>
                    <h3 class="font-serif text-xl font-semibold">Offen für alle</h3>
                    <p class="mt-2 text-ink/70">Ein Museum ist ein öffentlicher Raum für Begegnung, Bildung und Inspiration.</p>
                </div>
                <div>
                    <h3 class="font-serif text-xl font-semibold">Kunst bewahren</h3>
                    <p class="mt-2 text-ink/70">Sammlungen pflegen, Werke zeigen und kommende Generationen erreichen.</p>
                </div>
                <div>
                    <h3 class="font-serif text-xl font-semibold">Deine Stimme zählt</h3>
                    <p class="mt-2 text-ink/70">Zeig öffentlich, was dir das Museum bedeutet — mit einem Bild, das nur du gestaltest.</p>
                </div>
            </div>
        </section>

        {{-- Generator --}}
        <section id="mitmachen" class="mx-auto max-w-3xl px-6 py-20">
            <div class="mb-8 text-center">
                <h2 class="font-serif text-3xl font-semibold sm:text-4xl">Gestalte dein «Ja»</h2>
                <p class="mx-auto mt-3 max-w-xl text-ink/70">
                    Beschreibe, wie dein persönliches Plakat aussehen soll — wir bringen es mit KI zum Leben.
                </p>
            </div>

            {{-- Vue island mounts here --}}
            <div id="image-generator"></div>
        </section>
    </main>

    <footer class="border-t border-ink/10">
        <div class="mx-auto max-w-5xl px-6 py-8 text-sm text-ink/50">
            Prototyp · Kunstmuseum-Kampagne · Bilder werden mit KI generiert.
        </div>
    </footer>

</body>
</html>
