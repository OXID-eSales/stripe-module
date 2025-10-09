#!/bin/bash

#############################################
# Payment Component Presentation Converter
# Converts PRESENTATION.md to PPTX/PDF/HTML
#############################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "================================================"
echo "Payment Component Presentation Converter"
echo "================================================"
echo ""

# Check if source file exists
if [ ! -f "PRESENTATION.md" ]; then
    echo "❌ ERROR: PRESENTATION.md not found in current directory"
    exit 1
fi

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to convert using Marp CLI
convert_with_marp() {
    echo "🔄 Converting with Marp CLI..."

    # Check if marp is installed
    if ! command_exists marp; then
        echo "❌ Marp CLI not found. Installing..."

        if command_exists npm; then
            npm install -g @marp-team/marp-cli
        else
            echo "❌ npm not found. Please install Node.js first."
            echo "   Visit: https://nodejs.org/"
            return 1
        fi
    fi

    # Convert to PPTX
    echo "   → Creating PRESENTATION.pptx..."
    marp PRESENTATION.md --pptx -o PRESENTATION.pptx --allow-local-files

    # Convert to PDF
    echo "   → Creating PRESENTATION.pdf..."
    marp PRESENTATION.md --pdf -o PRESENTATION.pdf --allow-local-files

    # Convert to HTML
    echo "   → Creating PRESENTATION.html..."
    marp PRESENTATION.md --html -o PRESENTATION.html --allow-local-files

    echo "✅ Conversion complete!"
    echo ""
    echo "📁 Output files:"
    echo "   - PRESENTATION.pptx (PowerPoint - editable)"
    echo "   - PRESENTATION.pdf (PDF - print-ready)"
    echo "   - PRESENTATION.html (HTML - web viewing)"

    return 0
}

# Function to convert using Docker
convert_with_docker() {
    echo "🔄 Converting with Docker (Marp)..."

    # Check if docker is installed
    if ! command_exists docker; then
        echo "❌ Docker not found. Please install Docker first."
        echo "   Visit: https://docs.docker.com/get-docker/"
        return 1
    fi

    # Convert to PPTX
    echo "   → Creating PRESENTATION.pptx..."
    docker run --rm -v "$(pwd):/home/marp/app/" marpteam/marp-cli \
        PRESENTATION.md --pptx -o PRESENTATION.pptx --allow-local-files

    # Convert to PDF
    echo "   → Creating PRESENTATION.pdf..."
    docker run --rm -v "$(pwd):/home/marp/app/" marpteam/marp-cli \
        PRESENTATION.md --pdf -o PRESENTATION.pdf --allow-local-files

    # Convert to HTML
    echo "   → Creating PRESENTATION.html..."
    docker run --rm -v "$(pwd):/home/marp/app/" marpteam/marp-cli \
        PRESENTATION.md --html -o PRESENTATION.html --allow-local-files

    echo "✅ Conversion complete!"
    echo ""
    echo "📁 Output files:"
    echo "   - PRESENTATION.pptx (PowerPoint - editable)"
    echo "   - PRESENTATION.pdf (PDF - print-ready)"
    echo "   - PRESENTATION.html (HTML - web viewing)"

    return 0
}

# Function to convert using Pandoc
convert_with_pandoc() {
    echo "🔄 Converting with Pandoc..."

    # Check if pandoc is installed
    if ! command_exists pandoc; then
        echo "❌ Pandoc not found. Installing..."

        if command_exists apt-get; then
            sudo apt-get update
            sudo apt-get install -y pandoc
        elif command_exists brew; then
            brew install pandoc
        else
            echo "❌ Cannot install Pandoc automatically."
            echo "   Please install manually: https://pandoc.org/installing.html"
            return 1
        fi
    fi

    # Convert to PPTX
    echo "   → Creating PRESENTATION.pptx..."
    pandoc PRESENTATION.md -o PRESENTATION.pptx

    # Convert to PDF (requires LaTeX)
    if command_exists pdflatex; then
        echo "   → Creating PRESENTATION.pdf..."
        pandoc PRESENTATION.md -t beamer -o PRESENTATION.pdf
    else
        echo "   ⚠️  Skipping PDF (LaTeX not installed)"
    fi

    echo "✅ Conversion complete!"
    echo ""
    echo "📁 Output files:"
    echo "   - PRESENTATION.pptx (PowerPoint - editable)"
    [ -f PRESENTATION.pdf ] && echo "   - PRESENTATION.pdf (PDF - print-ready)"

    return 0
}

# Main menu
echo "Select conversion method:"
echo ""
echo "1) Marp CLI (Recommended - best layout preservation)"
echo "2) Docker + Marp (No installation needed)"
echo "3) Pandoc (Alternative, more customizable)"
echo "4) Auto-detect (try methods in order)"
echo ""
read -p "Enter choice [1-4]: " choice

case $choice in
    1)
        convert_with_marp
        ;;
    2)
        convert_with_docker
        ;;
    3)
        convert_with_pandoc
        ;;
    4)
        echo "🔍 Auto-detecting available tools..."
        echo ""

        if command_exists marp; then
            echo "✅ Found: Marp CLI"
            convert_with_marp
        elif command_exists docker; then
            echo "✅ Found: Docker"
            convert_with_docker
        elif command_exists pandoc; then
            echo "✅ Found: Pandoc"
            convert_with_pandoc
        else
            echo "❌ No conversion tools found."
            echo ""
            echo "Please install one of:"
            echo "  - Marp CLI: npm install -g @marp-team/marp-cli"
            echo "  - Docker: https://docs.docker.com/get-docker/"
            echo "  - Pandoc: https://pandoc.org/installing.html"
            echo ""
            echo "Or use the web interface: https://web.marp.app/"
            exit 1
        fi
        ;;
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "================================================"
echo "✅ Done! Your presentation is ready."
echo "================================================"
echo ""
echo "Next steps:"
echo "  1. Open PRESENTATION.pptx in PowerPoint/LibreOffice"
echo "  2. Review and customize (add logo, adjust colors)"
echo "  3. Practice delivery (20-25 minutes)"
echo "  4. Share with stakeholders"
echo ""
echo "Need help? Check PRESENTATION-GUIDE.md"
echo ""
