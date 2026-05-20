from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Image, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle
from reportlab.lib.styles import ParagraphStyle
import os


language_fonts = {
    1: ("Devanagari_Condensed-Black.ttf", "dothindi.ttf"),
}

languag_name = {
    1: "Hindi",
}


def _resolve_font_path(font_name_or_path):
    if os.path.isabs(font_name_or_path):
        return font_name_or_path
    return os.path.join(os.path.dirname(__file__), "font", font_name_or_path)


def _register_language_fonts(language_id):
    if language_id not in language_fonts:
        raise ValueError(f"Unsupported language_id: {language_id}")

    bold_font_path, dotted_font_path = language_fonts[language_id]
    bold_font_path = _resolve_font_path(bold_font_path)
    dotted_font_path = _resolve_font_path(dotted_font_path)

    if not os.path.exists(bold_font_path):
        raise FileNotFoundError(f"Bold font not found: {bold_font_path}")
    if not os.path.exists(dotted_font_path):
        raise FileNotFoundError(f"Dotted font not found: {dotted_font_path}")

    bold_font_name = f"Bold_{language_id}"
    dotted_font_name = f"Dotted_{language_id}"

    if bold_font_name not in pdfmetrics.getRegisteredFontNames():
        pdfmetrics.registerFont(TTFont(bold_font_name, bold_font_path))
    if dotted_font_name not in pdfmetrics.getRegisteredFontNames():
        pdfmetrics.registerFont(TTFont(dotted_font_name, dotted_font_path))

    return bold_font_name, dotted_font_name


def _safe_scaled_image(image_path, max_width, max_height):
    if not os.path.exists(image_path):
        return Paragraph("Image not found", ParagraphStyle("missing_image", fontName="Helvetica", fontSize=14))

    img = Image(image_path)
    width_ratio = max_width / img.imageWidth
    height_ratio = max_height / img.imageHeight
    scale_ratio = min(width_ratio, height_ratio)
    img.drawWidth = img.imageWidth * scale_ratio
    img.drawHeight = img.imageHeight * scale_ratio
    return img


def _draw_watermark(canvas, doc):
    canvas.saveState()
    page_width, page_height = A4
    canvas.translate(page_width / 2.0, page_height / 2.0)
    canvas.rotate(45)
    try:
        watermark_color = colors.Color(0.5, 0.5, 0.5, alpha=0.12)
    except TypeError:
        watermark_color = colors.Color(0.5, 0.5, 0.5)
    canvas.setFillColor(watermark_color)
    canvas.setFont("Helvetica-Bold", 72)
    canvas.drawCentredString(0, 0, "Desibhasha")
    canvas.restoreState()


def create_learning_pdf(
    character,
    word,
    english_word,
    image_path,
    language_id=1,
    output_filename=None,
    allow_multi_page=False,
):
    if not output_filename:
        language_label = languag_name.get(language_id, "Language")
        output_filename = f"worksheet_{character}_{language_label}.pdf"

    bold_font, dotted_font = _register_language_fonts(language_id)

    doc = SimpleDocTemplate(
        output_filename,
        pagesize=A4,
        leftMargin=28,
        rightMargin=28,
        topMargin=5,
        bottomMargin=28,
    )
    elements = []

    section_title_style = ParagraphStyle(
        "section_title",
        fontName="Helvetica-Bold",
        fontSize=14,
        leading=18,
        textColor=colors.black,
        spaceAfter=8,
    )
    char_style = ParagraphStyle(
        "char_style",
        fontName=bold_font,
        fontSize=140,
        leading=120,
        alignment=1,
    )
    word_small_style = ParagraphStyle(
        "word_small_style",
        fontName=bold_font,
        fontSize=28,
        leading=32,
        alignment=1,
    )
    word_trace_normal_style = ParagraphStyle(
        "word_trace_normal_style",
        fontName=bold_font,
        fontSize=34,
        leading=40,
        alignment=1,
    )
    word_trace_dotted_style = ParagraphStyle(
        "word_trace_dotted_style",
        fontName=dotted_font,
        fontSize=40,
        leading=48,
        alignment=1,
    )
    english_word_style = ParagraphStyle(
        "english_word_style",
        fontName="Helvetica-Bold",
        fontSize=13,
        leading=16,
        alignment=1,
        textColor=colors.HexColor("#444444"),
    )
    s2_text_style_bold = ParagraphStyle(
        "s2_text_bold",
        fontName=bold_font,
        fontSize=60,
        leading=1,
        alignment=1,
        spaceBefore=0,
        spaceAfter=0,
    )
    s2_text_style_dotted = ParagraphStyle(
        "s2_text_dotted",
        fontName=dotted_font,
        fontSize=60,
        leading=1,
        alignment=1,
        spaceBefore=0,
        spaceAfter=0,
    )
    s3_text_style_bold = ParagraphStyle(
        "s3_text_bold",
        fontName=bold_font,
        fontSize=80,
        leading=1,
        alignment=1,
        spaceBefore=0,
        spaceAfter=0,
    )
    s3_text_style_dotted = ParagraphStyle(
        "s3_text_dotted",
        fontName=dotted_font,
        fontSize=80,
        leading=1,
        alignment=1,
        spaceBefore=0,
        spaceAfter=0,
    )

    def _max_font_for_width(text, font_name, avail_width, max_fs=66, min_fs=20):
        word_clean = (text or "").strip()
        if not word_clean:
            return min_fs
        for fs in range(max_fs, min_fs - 1, -1):
            if pdfmetrics.stringWidth(word_clean, font_name, fs) <= avail_width - 10:
                return fs
        return min_fs

    def _dynamic_trace_text(input_word, font_name, font_size, table_width):
        cleaned_word = (input_word or "").strip()
        if not cleaned_word:
            return ""

        character_count = len(cleaned_word)
        token = cleaned_word + " "
        try:
            token_width = pdfmetrics.stringWidth(token, font_name, font_size)
        except Exception:
            token_width = max(40, character_count * (font_size * 0.7))

        usable_width = max(80, table_width - 18)
        repeat_count = max(1, int(usable_width // max(token_width, 1)))
        return token * repeat_count

    # elements.append(Paragraph("Section 1: Character + Image + Word", section_title_style))

    left_content = Table(
        [[Paragraph(character, char_style)]],
        colWidths=[250],
        rowHeights=[180],
    )
    left_content.setStyle(
        TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ])
    )

    image_flowable = _safe_scaled_image(image_path, max_width=255, max_height=122)

    right_content = Table(
        [
            [image_flowable],
            [Paragraph(word or "", word_small_style)],
            [Paragraph(english_word or "", english_word_style)],
        ],
        colWidths=[255],
        rowHeights=[122, 32, 26],
    )
    right_content.setStyle(
        TableStyle([
            ("VALIGN", (0, 0), (0, 0), "TOP"),
            ("VALIGN", (0, 1), (-1, -1), "MIDDLE"),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ])
    )

    hero_table = Table(
        [[left_content, right_content]],
        colWidths=[255, 255],
        rowHeights=[196],
    )
    hero_table.setStyle(
        TableStyle([
            ("GRID", (0, 0), (-1, -1), 1, colors.grey),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ])
    )
    elements.append(hero_table)
    elements.append(Spacer(1, 8))

    # Section 2: Character Practice
    practice_rows = []
    for _ in range(8):
        practice_rows.append([character, character, character, character, character, character])

    s2_bold_fs = _max_font_for_width(character, bold_font, 85, max_fs=60)
    s2_dotted_fit_fs = _max_font_for_width(character, dotted_font, 85, max_fs=80)
    # Keep dotted glyphs visibly larger than bold while staying inside cell width.
    s2_dotted_fs = max(s2_bold_fs + 6, s2_dotted_fit_fs)

    practice_table = Table(practice_rows, colWidths=[85, 85, 85, 85, 85, 85], rowHeights=54)
    practice_table.setStyle(
        TableStyle([
            ("FONTNAME", (0, 0), (0, -1), bold_font),
            ("FONTNAME", (1, 0), (-1, -1), dotted_font),
            ("FONTSIZE", (0, 0), (0, -1), s2_bold_fs),
            ("FONTSIZE", (1, 0), (-1, -1), s2_dotted_fs),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (0, -1), -10),
            ("TOPPADDING", (1, 0), (-1, -1), -30),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 2),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("GRID", (0, 0), (-1, -1), 0.8, colors.grey),
            ("BACKGROUND", (0, 0), (0, -1), colors.whitesmoke),
        ])
    )
    elements.append(practice_table)
    elements.append(Spacer(1, 8))

    # elements.append(Paragraph("Section 3: Word Tracing", section_title_style))
    tracing_table_width = 510
    first_col_width = 130
    remaining_width = max(120, tracing_table_width - first_col_width)

    # Dynamic font: larger font for short words, smaller for long words
    s3_bold_fs = _max_font_for_width(word, bold_font, first_col_width - 8, max_fs=80)
    s3_dotted_fs = _max_font_for_width(word, dotted_font, (remaining_width // 3) - 8, max_fs=80)

    # Fixed 4 columns: 1 guide + 3 practice
    dotted_col_count = 3
    dotted_col_width = remaining_width / dotted_col_count
    col_widths = [first_col_width] + [dotted_col_width] * dotted_col_count

    word_guide = Paragraph(word or "", word_trace_normal_style)
    trace_word = Paragraph(word or "", word_trace_dotted_style)

    table_rows = []
    for _ in range(3):
        row = [word_guide] + [trace_word] * dotted_col_count
        table_rows.append(row)

    tracing_table = Table(table_rows, colWidths=col_widths, rowHeights=[50, 50, 50])
    tracing_table.setStyle(
        TableStyle([
            ("FONTNAME", (0, 0), (0, -1), bold_font),
            ("FONTNAME", (1, 0), (-1, -1), dotted_font),
            ("FONTSIZE", (0, 0), (0, -1), s3_bold_fs),
            ("FONTSIZE", (1, 0), (-1, -1), s3_dotted_fs),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("VALIGN", (0, 0), (-1, -1), "W"),
            ("TOPPADDING", (0, 0), (-1, -1), 2),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 2),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("GRID", (0, 0), (-1, -1), 0.8, colors.grey),
            ("BACKGROUND", (0, 0), (0, -1), colors.whitesmoke),
        ])
    )
    elements.append(tracing_table)

    def _on_page(canvas, doc):
        if not allow_multi_page and canvas.getPageNumber() > 1:
            raise ValueError(
                "Content exceeds a single page. Pass allow_multi_page=True to allow multiple pages."
            )
        _draw_watermark(canvas, doc)

    doc.build(elements, onFirstPage=_on_page, onLaterPages=_on_page)
    print(f"PDF created: {output_filename}")


if __name__ == "__main__":
    create_learning_pdf(
        character="अ",
        word="अग्नि",
        english_word="Fire",
        image_path=r"C:\Users\pc\Desktop\python\desibhasha-scripts\image-script\anar.jfif",
        language_id=1,
    )