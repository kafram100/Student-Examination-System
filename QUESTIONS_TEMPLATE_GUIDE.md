# Questions Import Template Guide

## Overview
The CSV template allows lecturers to bulk import questions into the exam system. The template supports 4 question types: **MCQ**, **Fill-in-the-Blank**, **Theory**, and **File Upload**.

## Downloading the Template
1. Navigate to your exam in the lecturer dashboard
2. Click the **"Template"** button (download icon)
3. Save the `questions_template.csv` file to your computer

## CSV Format

### Column Structure
```
question_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, marks
```

### Question Types

#### 1. **MCQ (Multiple Choice Questions)**
- **question_type**: `mcq`
- **Required fields**: question_text, option_a, option_b, correct_answer
- **correct_answer format**: Must be A, B, C, D, or E (uppercase)
- **Example**:
```csv
mcq,"What is 2+2?",1,2,3,4,,D,1
mcq,"Which planet is known as the Red Planet?",Earth,Mars,Jupiter,Saturn,,B,2
```

#### 2. **Fill-in-the-Blank**
- **question_type**: `fill_in`
- **Required fields**: question_text, correct_answer
- **correct_answer format**: The exact answer text (case-sensitive)
- **Example**:
```csv
fill_in,"The capital of France is ______.",,,,,,Paris,2
fill_in,"Water freezes at ______ degrees Celsius.",,,,,,0,1
```

#### 3. **Theory/Short Answer**
- **question_type**: `theory`
- **Required fields**: question_text
- **correct_answer**: Leave empty (will be manually graded)
- **Example**:
```csv
theory,"Explain the concept of object-oriented programming.",,,,,,,5
theory,"Describe the water cycle in detail.",,,,,,,10
```

#### 4. **File Upload**
- **question_type**: `file`
- **Required fields**: question_text
- **correct_answer**: Leave empty (students upload files)
- **Example**:
```csv
file,"Upload your project documentation here.",,,,,,,10
file,"Submit your assignment as a PDF file.",,,,,,,20
```

## Complete Example CSV

```csv
question_type,question_text,option_a,option_b,option_c,option_d,option_e,correct_answer,marks
mcq,"What is the chemical symbol for water?",H2O,CO2,O2,NaCl,,A,1
mcq,"Who wrote 'Romeo and Juliet'?",Charles Dickens,William Shakespeare,Mark Twain,Jane Austen,,B,2
fill_in,"The speed of light is approximately ______ km/s.",,,,,,300000,3
fill_in,"PHP stands for ______.",,,,,,PHP: Hypertext Preprocessor,2
theory,"Explain the differences between HTTP and HTTPS.",,,,,,,5
theory,"Discuss the impact of climate change on biodiversity.",,,,,,,10
file,"Upload your final project report (PDF format).",,,,,,,50
```

## Important Notes

### Formatting Rules
1. **Text in quotes**: If your question text contains commas, enclose it in double quotes
2. **Empty fields**: Leave option fields empty for fill-in, theory, and file questions
3. **Marks**: Must be a positive integer (minimum 1)
4. **Question type**: Must be lowercase (`mcq`, `fill_in`, `theory`, `file`)

### Validation Requirements
- **MCQ**: Must have at least 2 options (A and B), correct answer must be A/B/C/D/E
- **Fill-in**: Must have a correct answer specified
- **Theory/File**: No correct answer needed (will be manually graded)
- All questions must have question text

### Common Mistakes to Avoid
❌ Using lowercase for MCQ correct answers (use A, not a)  
❌ Missing question_type column  
❌ Not providing correct answer for fill-in questions  
❌ Adding options to theory/file questions unnecessarily  
✅ Always download the template first to see the structure  
✅ Test with a few questions before importing large batches  

## How to Import

1. **Prepare your CSV** using the downloaded template
2. **Navigate** to your exam → Click "Import CSV"
3. **Select your CSV file** and click "Upload & Import"
4. **Review results** - you'll see how many questions were successfully imported
5. **Verify questions** in the exam view before publishing

## Troubleshooting

**Questions not importing?**
- Check that question_type is one of: `mcq`, `fill_in`, `theory`, `file`
- Ensure minimum required columns are present
- Verify MCQ correct answers are A/B/C/D/E
- Check that fill-in questions have correct answers

**CSV format errors?**
- Open the template in Excel, Google Sheets, or LibreOffice Calc
- Save as CSV (Comma delimited) format
- Ensure UTF-8 encoding if using special characters

## Support
For issues or questions, contact your system administrator.
