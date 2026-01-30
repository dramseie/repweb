#!/usr/bin/env python3
import json
import sys
from pathlib import Path

try:
    from pptx import Presentation
    from pptx.util import Inches, Pt
except ImportError as exc:
    raise SystemExit("python-pptx is required. Install with: pip install python-pptx") from exc


def get_country_blocks(section):
    return {item.get("country"): item for item in section.get("items", [])}


def add_table(slide, left, top, width, rows):
    cols = ["Site ID", "Site Name", "Start", "End", "Confidence", "Status"]
    row_count = max(2, len(rows) + 1)
    table_shape = slide.shapes.add_table(row_count, len(cols), left, top, width, Inches(1.1))
    table = table_shape.table

    for idx, col in enumerate(cols):
        cell = table.cell(0, idx)
        cell.text = col
        for paragraph in cell.text_frame.paragraphs:
            paragraph.font.bold = True
            paragraph.font.size = Pt(10)

    for r_idx, row in enumerate(rows, start=1):
        values = [
            row.get("siteId") or "",
            row.get("siteName") or "",
            row.get("startDate") or "",
            row.get("endDate") or "",
            row.get("confidence") or "",
            row.get("status") or "",
        ]
        for c_idx, value in enumerate(values):
            cell = table.cell(r_idx, c_idx)
            cell.text = str(value)
            for paragraph in cell.text_frame.paragraphs:
                paragraph.font.size = Pt(9)

    return table_shape


def add_section(slide, y, title, meta, current_rows, next_rows):
    title_box = slide.shapes.add_textbox(Inches(0.6), y, Inches(12.0), Inches(0.3))
    title_tf = title_box.text_frame
    title_tf.text = title
    title_tf.paragraphs[0].font.bold = True
    title_tf.paragraphs[0].font.size = Pt(14)

    month_left = meta.get("currentMonth", "Current")
    month_right = meta.get("nextMonth", "Next")

    left_label = slide.shapes.add_textbox(Inches(0.6), y + Inches(0.35), Inches(5.6), Inches(0.2))
    left_label.text_frame.text = month_left
    left_label.text_frame.paragraphs[0].font.size = Pt(10)

    right_label = slide.shapes.add_textbox(Inches(6.3), y + Inches(0.35), Inches(5.6), Inches(0.2))
    right_label.text_frame.text = month_right
    right_label.text_frame.paragraphs[0].font.size = Pt(10)

    if current_rows:
        add_table(slide, Inches(0.6), y + Inches(0.6), Inches(5.6), current_rows)
    else:
        empty_left = slide.shapes.add_textbox(Inches(0.6), y + Inches(0.6), Inches(5.6), Inches(0.3))
        empty_left.text_frame.text = "No planned assessments."
        empty_left.text_frame.paragraphs[0].font.size = Pt(9)

    if next_rows:
        add_table(slide, Inches(6.3), y + Inches(0.6), Inches(5.6), next_rows)
    else:
        empty_right = slide.shapes.add_textbox(Inches(6.3), y + Inches(0.6), Inches(5.6), Inches(0.3))
        empty_right.text_frame.text = "No planned assessments."
        empty_right.text_frame.paragraphs[0].font.size = Pt(9)


def add_issue_table(slide, left, top, width, rows):
    cols = [
        "Store Name",
        "Store ID",
        "Description",
        "Priority (CHML)",
        "Responsible Party",
        "Action to be taken (DD.MM.YY - NS)",
        "Date to be resolved (DD.MM.YY)",
    ]
    row_count = max(2, len(rows) + 1)
    table_shape = slide.shapes.add_table(row_count, len(cols), left, top, width, Inches(3.6))
    table = table_shape.table

    for idx, col in enumerate(cols):
        cell = table.cell(0, idx)
        cell.text = col
        for paragraph in cell.text_frame.paragraphs:
            paragraph.font.bold = True
            paragraph.font.size = Pt(9)

    for r_idx, row in enumerate(rows, start=1):
        values = [
            row.get("storeName") or "",
            row.get("storeId") or "",
            row.get("description") or "",
            row.get("priority") or "",
            row.get("responsibleParty") or "",
            row.get("actionRequired") or "",
            row.get("resolveDate") or "",
        ]
        for c_idx, value in enumerate(values):
            cell = table.cell(r_idx, c_idx)
            cell.text = str(value)
            for paragraph in cell.text_frame.paragraphs:
                paragraph.font.size = Pt(8)

    return table_shape


def main(input_path, output_path):
    data = json.loads(Path(input_path).read_text(encoding="utf-8"))
    prs = Presentation()

    assessments = data.get("plannedAssessments", {})
    installations = data.get("plannedInstallations", {})
    post_deployment = data.get("postDeployment", {})
    issue_log = data.get("issueLog", {})

    assessment_blocks = get_country_blocks(assessments)
    installation_blocks = get_country_blocks(installations)
    post_blocks = get_country_blocks(post_deployment)
    issue_blocks = get_country_blocks(issue_log)

    country_list = data.get("countries") or sorted(
        {
            c
            for c in list(assessment_blocks.keys())
            + list(installation_blocks.keys())
            + list(post_blocks.keys())
            + list(issue_blocks.keys())
            if c
        }
    )

    for country in country_list:
        slide = prs.slides.add_slide(prs.slide_layouts[6])
        title = slide.shapes.add_textbox(Inches(0.6), Inches(0.3), Inches(12.0), Inches(0.5))
        title.text_frame.text = country
        title.text_frame.paragraphs[0].font.size = Pt(20)
        title.text_frame.paragraphs[0].font.bold = True

        assessment = assessment_blocks.get(country, {})
        installation = installation_blocks.get(country, {})
        post = post_blocks.get(country, {})

        add_section(
            slide,
            Inches(1.0),
            "Planned Assessments",
            assessments.get("meta", {}),
            assessment.get("current", []),
            assessment.get("next", []),
        )

        add_section(
            slide,
            Inches(3.1),
            "Planned Installations",
            installations.get("meta", {}),
            installation.get("current", []),
            installation.get("next", []),
        )

        add_section(
            slide,
            Inches(5.2),
            "Post-Deployment & Sign-off",
            post_deployment.get("meta", {}),
            post.get("current", []),
            post.get("next", []),
        )

        issues = issue_blocks.get(country, {})
        if issues.get("issues"):
            issue_slide = prs.slides.add_slide(prs.slide_layouts[6])
            title = issue_slide.shapes.add_textbox(Inches(0.6), Inches(0.3), Inches(12.0), Inches(0.5))
            title.text_frame.text = f"{country} Issues"
            title.text_frame.paragraphs[0].font.size = Pt(20)
            title.text_frame.paragraphs[0].font.bold = True

            add_issue_table(issue_slide, Inches(0.6), Inches(1.2), Inches(12.0), issues.get("issues", []))

    prs.save(output_path)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("Usage: presentation_export.py <input.json> <output.pptx>")
    main(sys.argv[1], sys.argv[2])
