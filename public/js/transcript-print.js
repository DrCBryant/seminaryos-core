const printButton = document.getElementById('transcript-print');

async function enablePrinting() {
    await document.fonts.ready;
    printButton.disabled = false;
    printButton.addEventListener('click', () => window.print());
}

enablePrinting();
