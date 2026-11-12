#!/usr/bin/env python3
"""
Royal Nest Café & Restaurant - Production Deployment Packager
Creates a deployment-ready ZIP archive for live server / cPanel / VPS deployment.
"""

import os
import sys
import zipfile
import shutil
from pathlib import Path

APP_DIR = Path(__file__).resolve().parent
DIST_DIR = APP_DIR / "dist"
OUTPUT_ZIP = DIST_DIR / "royal_nest_production_deployment.zip"

INCLUDE_DIRS = [
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "storage",
    "docs",
]

INCLUDE_FILES = [
    "artisan",
    "composer.json",
    "composer.lock",
    ".env.production.example",
]

EXCLUDE_PATTERNS = [
    "*.pyc",
    "__pycache__",
    ".DS_Store",
    "Thumbs.db",
    "storage/framework/cache/data/*",
    "storage/framework/sessions/*",
    "storage/framework/views/*",
    "storage/logs/*.log",
    "node_modules",
    ".git",
    ".env",
    "tests",
]

def create_package():
    print("=" * 60)
    print("Packing Royal Nest Production Release Bundle...")
    print("=" * 60)

    # 1. Verify Vite production build exists
    manifest_path = APP_DIR / "public" / "build" / "manifest.json"
    if not manifest_path.exists():
        print("ERROR: Vite production assets not found in public/build! Run 'npm run build' first.")
        sys.exit(1)

    # 2. Ensure dist directory exists
    DIST_DIR.mkdir(parents=True, exist_ok=True)
    if OUTPUT_ZIP.exists():
        OUTPUT_ZIP.unlink()

    file_count = 0
    with zipfile.ZipFile(OUTPUT_ZIP, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # Add root files
        for fname in INCLUDE_FILES:
            fpath = APP_DIR / fname
            if fpath.exists():
                zipf.write(fpath, arcname=fname)
                file_count += 1
                print(f"Added file: {fname}")

        # Add directories
        for dname in INCLUDE_DIRS:
            dir_path = APP_DIR / dname
            if not dir_path.exists():
                continue

            for root, dirs, files in os.walk(dir_path):
                # Filter out excluded directory patterns
                dirs[:] = [d for d in dirs if d not in [".git", "node_modules", "tests", "__pycache__"]]

                for file in files:
                    full_path = Path(root) / file
                    rel_path = full_path.relative_to(APP_DIR)

                    # Check ignore rules
                    if any(full_path.match(pat) for pat in EXCLUDE_PATTERNS):
                        continue

                    zipf.write(full_path, arcname=str(rel_path))
                    file_count += 1

    zip_size_mb = OUTPUT_ZIP.stat().st_size / (1024 * 1024)
    print("=" * 60)
    print(f"SUCCESS: Package generated successfully!")
    print(f"Location: {OUTPUT_ZIP}")
    print(f"Total Files: {file_count}")
    print(f"Archive Size: {zip_size_mb:.2f} MB")
    print("=" * 60)

if __name__ == "__main__":
    create_package()
