svg:
	docker run --rm -v $$(pwd):/workspace plantuml/plantuml -tsvg /workspace/puml/*.puml -o /workspace/_generated/

.PHONY: svg clean

clean:
	rm -f _generated/*.svg

help:
	@echo "Available targets:"
	@echo "  svg    - Generate SVG files from all PUML diagrams"
	@echo "  clean  - Remove all generated SVG files"
	@echo "  help   - Show this help message"
