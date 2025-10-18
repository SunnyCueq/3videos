#!/usr/bin/env python3
"""
Script to add declare(strict_types=1) to all PHP files
"""

import os
import re
from pathlib import Path

def add_strict_types_to_file(filepath):
    """Add declare(strict_types=1) after the opening <?php tag"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Check if already has declare(strict_types=1)
        if 'declare(strict_types=1)' in content:
            print(f"✓ {filepath} - bereits vorhanden")
            return False
        
        # Skip template files
        if '\\n *' not in content and '<?php' not in content:
            print(f"⊘ {filepath} - kein PHP Code")
            return False
            
        # Find the end of the header comment block and add declare
        pattern = r'(\*+/\s*\n)(if \(!defined|include|define|require|\$)'
        
        if re.search(pattern, content):
            new_content = re.sub(
                pattern,
                r'\1\ndeclare(strict_types=1);\n\n\2',
                content,
                count=1
            )
        else:
            # Simple case: add after <?php
            pattern_simple = r'(<\?php\s*\n)'
            new_content = re.sub(
                pattern_simple,
                r'\1\ndeclare(strict_types=1);\n',
                content,
                count=1
            )
        
        if new_content != content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"✓ {filepath} - hinzugefügt")
            return True
        else:
            print(f"⊘ {filepath} - keine Änderung")
            return False
            
    except Exception as e:
        print(f"✗ {filepath} - Fehler: {e}")
        return False

def main():
    """Main function"""
    # Directories to process
    dirs = [
        'includes',
        'admin',
        '.'  # Root directory
    ]
    
    # Skip these files
    skip_files = {
        'config.php',
        'config.new.php',
        'install.php',
        'db_field_definitions.php',
        'constants.php',
        'upload_definitions.php'
    }
    
    total = 0
    modified = 0
    
    for directory in dirs:
        if directory == '.':
            # Only root level PHP files
            php_files = [f for f in Path(directory).glob('*.php') 
                        if f.name not in skip_files and f.is_file()]
        else:
            # All PHP files in subdirectory
            php_files = [f for f in Path(directory).rglob('*.php') 
                        if f.name not in skip_files and f.is_file()]
        
        for php_file in sorted(php_files):
            total += 1
            if add_strict_types_to_file(str(php_file)):
                modified += 1
    
    print(f"\n{'='*60}")
    print(f"Gesamt: {total} Dateien geprüft")
    print(f"Geändert: {modified} Dateien")
    print(f"Übersprungen: {total - modified} Dateien")
    print(f"{'='*60}")

if __name__ == '__main__':
    main()
