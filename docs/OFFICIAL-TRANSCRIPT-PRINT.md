# Official transcript print and export

In **Official Transcripts**, open **View Official Transcript**, then select **Print / Save PDF**. On the print page, select **Print / Save PDF** again and choose the browser’s **Save as PDF** destination. Keep the default scale and disable browser headers and footers. The page title suggests the download filename.

The print page requires an active user with an active registrar-panel membership in the selected institution, plus permission to view the issued record. It sends private, no-store responses. The preview and print page share one Blade document and read frozen issuance data. Legacy notices remain visible on the export, and missing historical fields remain omitted. Per-course grade points and status follow captured settings. Aggregate GPA and automatic award selection stay governed by separate institutional decisions. Printing performs no application-data writes.

## Layout and browser support

Print CSS sets US Letter portrait with 0.8-inch margins. Term tables repeat their headings as they continue and keep individual course rows together. Chromium-based browsers with CSS page-margin support print the transcript and student numbers in the footer and number each page. Browsers without page-margin support can still print the document and its first-page identification, though continuation footers or page counts may be absent. The browser can override layout if the operator chooses a different paper size or scaling. PDF creation timestamps and internals can vary between downloads.

The former CDN-loaded html2pdf.js bundle failed integrity validation and rasterized text. Native browser printing removes that dependency, retains searchable text, and avoids server PDF infrastructure. The print action waits for local fonts before enabling the button. Browser Print remains available if JavaScript is disabled.

## Font licensing

Cinzel is the institutional heading fallback. SeminaryOS ships no Trajan Pro files or entitlement. An institution that wants Trajan Pro must obtain rights covering its web deployment and PDF embedding before supplying an asset and updating the heading stylesheet.

Montserrat is used for body text and labels. Source Code Pro is used for course entries and academic results. The three bundled fonts are unmodified upstream TrueType files from [Google Fonts revision 06bfe0d](https://github.com/google/fonts/tree/06bfe0d62a98375cb09c942fa18e68bd32cff057/ofl), distributed under SIL Open Font License 1.1. Their original notices are in `public/fonts/transcripts/`. The stylesheet provides Georgia, Arial, and monospace fallbacks for unavailable glyphs or fonts.

## Verification

For a print regression check, use issued fixtures for a short record, a long record with multiple terms and a wrapped course title, and a legacy record. Print each on Chromium with CSS paper size and browser headers disabled. Check US Letter dimensions, repeated identification and page counts, extractable Unicode text, embedded fonts, term grouping and totals, each course code appearing once, and absence of blank trailing pages. Compare extracted text for two downloads of the same issue. Check denied access separately for guests, inactive membership, drafts, and foreign institutions.
