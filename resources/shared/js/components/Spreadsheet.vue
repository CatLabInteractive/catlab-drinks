<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
    <div class="spreadsheet-wrapper">

        <div class="spreadsheet-container mb-3">
            <table class="table table-bordered table-sm spreadsheet-table" @keydown="handleKeydown">
                <thead>
                    <tr>
                        <th style="width: 50px">#</th>
                        <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                        <th style="width: 60px"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="row._key"
                        :class="{ 'table-warning': row._dirty, 'table-danger': row._deleted }">
                        <td class="row-number">{{ rowIndex + 1 }}</td>
                        <td v-for="(col, colIndex) in columns" :key="col.key"
                            :class="{ 'active-cell': activeRow === rowIndex && activeCol === colIndex }"
                            @click="activateCell(rowIndex, colIndex)">
                            <input
                                :ref="'cell-' + rowIndex + '-' + colIndex"
                                type="text"
                                class="cell-input"
                                v-model="row[col.key]"
                                @focus="activateCell(rowIndex, colIndex)"
                                @input="markDirty(rowIndex)"
                                @paste="handlePaste($event, rowIndex, colIndex)"
                                :disabled="row._deleted"
                            />
                        </td>
                        <td class="text-center">
                            <button v-if="!row._deleted && hasContent(row)"
                                class="btn btn-sm btn-outline-danger"
                                @click="deleteRow(rowIndex)"
                                title="Delete row">✕</button>
                            <button v-if="row._deleted"
                                class="btn btn-sm btn-outline-secondary"
                                @click="undeleteRow(rowIndex)"
                                title="Undo delete">↩</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-3">
            <b-btn variant="success" @click="$emit('save')" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save changes' }}
            </b-btn>
            <b-btn variant="light" @click="$emit('reset')">Reset</b-btn>
            <b-btn variant="outline-secondary" @click="addRows(5)">Add rows</b-btn>
            <b-btn variant="outline-danger" @click="confirmDeleteAll" class="float-right">Delete all</b-btn>

            <b-alert v-if="saved" variant="success" show class="d-inline-block ml-2 mb-0 py-1 px-2">Saved</b-alert>
        </div>

        <!-- Paste Import Dialog -->
        <b-modal ref="pasteDialog" title="Import pasted data" size="lg" @ok="applyPasteImport" @cancel="cancelPasteImport" ok-title="Import" cancel-title="Cancel">
            <div class="mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label"><strong>First column separator</strong></label>
                        <select class="form-control" v-model="pasteOptions.firstColumnSeparator" @change="updatePastePreview">
                            <option value=":">Colon (:)</option>
                            <option value="\t">Tab</option>
                            <option value=";">Semicolon (;)</option>
                            <option value=",">Comma (,)</option>
                            <option value="">None (use column separator for all)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Column separator</strong></label>
                        <select class="form-control" v-model="pasteOptions.columnSeparator" @change="updatePastePreview">
                            <option value="\t">Tab</option>
                            <option value=";">Semicolon (;)</option>
                            <option value=",">Comma (,)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="pastePreviewRows.length > 0">
                <label class="form-label"><strong>Preview</strong> ({{ pastePreviewRows.length }} rows)</label>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in pastePreviewRows" :key="index">
                                <td v-for="col in columns" :key="col.key">{{ row[col.key] || '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </b-modal>

    </div>
</template>

<script>

    let nextKey = 0;

    function createEmptyRow(columnKeys) {
        const row = { _key: nextKey++, _id: null, _dirty: false, _deleted: false };
        columnKeys.forEach(key => { row[key] = ''; });
        return row;
    }

    export default {
        props: {
            columns: {
                type: Array,
                required: true
                // Array of { key: string, label: string }
            },
            rows: {
                type: Array,
                required: true
            },
            saving: {
                type: Boolean,
                default: false
            },
            saved: {
                type: Boolean,
                default: false
            },
            emptyRowCount: {
                type: Number,
                default: 5
            }
        },

        data() {
            return {
                activeRow: -1,
                activeCol: -1,
                pasteOptions: {
                    firstColumnSeparator: ':',
                    columnSeparator: '\t'
                },
                pasteRawText: '',
                pasteTargetRow: 0,
                pasteTargetCol: 0,
                pastePreviewRows: []
            }
        },

        computed: {
            columnKeys() {
                return this.columns.map(c => c.key);
            }
        },

        methods: {

            activateCell(rowIndex, colIndex) {
                this.activeRow = rowIndex;
                this.activeCol = colIndex;
                this.$nextTick(() => {
                    const ref = this.$refs['cell-' + rowIndex + '-' + colIndex];
                    if (ref) {
                        const el = Array.isArray(ref) ? ref[0] : ref;
                        if (el && el.focus) el.focus();
                    }
                });
            },

            handleKeydown(e) {
                if (this.activeRow < 0 || this.activeCol < 0) return;

                let newRow = this.activeRow;
                let newCol = this.activeCol;
                const colCount = this.columns.length;

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    newRow = Math.max(0, this.activeRow - 1);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    newRow = Math.min(this.rows.length - 1, this.activeRow + 1);
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    if (this.activeCol < colCount - 1) {
                        newCol = this.activeCol + 1;
                    } else if (this.activeRow < this.rows.length - 1) {
                        newCol = 0;
                        newRow = this.activeRow + 1;
                    }
                    if (newRow === this.rows.length - 1 && newCol === colCount - 1) {
                        this.addRows(1);
                    }
                } else if (e.key === 'Tab' && e.shiftKey) {
                    e.preventDefault();
                    if (this.activeCol > 0) {
                        newCol = this.activeCol - 1;
                    } else if (this.activeRow > 0) {
                        newCol = colCount - 1;
                        newRow = this.activeRow - 1;
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    newRow = Math.min(this.rows.length - 1, this.activeRow + 1);
                    if (newRow === this.rows.length - 1) {
                        this.addRows(1);
                    }
                } else {
                    return;
                }

                this.activateCell(newRow, newCol);
            },

            handlePaste(e, rowIndex, colIndex) {
                const clipboardData = e.clipboardData || window.clipboardData;
                if (!clipboardData) return;

                const text = clipboardData.getData('text');
                if (!text) return;

                // Check if this is multi-cell data (contains tabs, colons, or newlines)
                const hasMultipleLines = text.indexOf('\n') !== -1;
                const hasTabs = text.indexOf('\t') !== -1;
                const hasColons = text.indexOf(':') !== -1;

                if (!hasMultipleLines && !hasTabs && !hasColons) {
                    return; // Single value, let default paste handle it
                }

                e.preventDefault();

                // Auto-detect best first column separator
                if (hasColons && hasMultipleLines) {
                    this.pasteOptions.firstColumnSeparator = ':';
                } else {
                    this.pasteOptions.firstColumnSeparator = '';
                }

                if (hasTabs) {
                    this.pasteOptions.columnSeparator = '\t';
                }

                this.pasteRawText = text;
                this.pasteTargetRow = rowIndex;
                this.pasteTargetCol = colIndex;
                this.updatePastePreview();
                this.$refs.pasteDialog.show();
            },

            parsePastedText(text, firstSep, colSep) {
                const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');
                const parsed = [];

                for (const line of lines) {
                    const row = {};
                    let remaining = line;
                    let colIdx = 0;

                    // Handle first column separator if set
                    if (firstSep && remaining.indexOf(firstSep) !== -1) {
                        const sepIndex = remaining.indexOf(firstSep);
                        if (colIdx < this.columnKeys.length) {
                            row[this.columnKeys[colIdx]] = remaining.substring(0, sepIndex).trim();
                        }
                        remaining = remaining.substring(sepIndex + firstSep.length);
                        colIdx++;
                    }

                    // Split remaining by column separator
                    const actualColSep = colSep === '\\t' ? '\t' : colSep;
                    const parts = remaining.split(actualColSep);

                    for (const part of parts) {
                        if (colIdx < this.columnKeys.length) {
                            row[this.columnKeys[colIdx]] = part.trim();
                        }
                        colIdx++;
                    }

                    // Fill any missing columns with empty strings
                    for (let i = colIdx; i < this.columnKeys.length; i++) {
                        row[this.columnKeys[i]] = '';
                    }

                    parsed.push(row);
                }

                return parsed;
            },

            updatePastePreview() {
                const firstSep = this.pasteOptions.firstColumnSeparator === '\\t' ? '\t' : this.pasteOptions.firstColumnSeparator;
                const colSep = this.pasteOptions.columnSeparator;
                this.pastePreviewRows = this.parsePastedText(this.pasteRawText, firstSep, colSep);
            },

            applyPasteImport() {
                const firstSep = this.pasteOptions.firstColumnSeparator === '\\t' ? '\t' : this.pasteOptions.firstColumnSeparator;
                const colSep = this.pasteOptions.columnSeparator;
                const parsedRows = this.parsePastedText(this.pasteRawText, firstSep, colSep);

                for (let i = 0; i < parsedRows.length; i++) {
                    const targetRow = this.pasteTargetRow + i;

                    // Add new rows if needed
                    while (targetRow >= this.rows.length) {
                        this.rows.push(createEmptyRow(this.columnKeys));
                    }

                    for (const key of this.columnKeys) {
                        if (parsedRows[i][key] !== undefined) {
                            this.rows[targetRow][key] = parsedRows[i][key];
                        }
                    }
                    this.markDirty(targetRow);
                }

                this.ensureEmptyRows();
                this.pasteRawText = '';
                this.$refs.pasteDialog.hide();
            },

            cancelPasteImport() {
                this.pasteRawText = '';
            },

            markDirty(rowIndex) {
                this.rows[rowIndex]._dirty = true;
            },

            hasContent(row) {
                return row._id || this.columnKeys.some(key => row[key]);
            },

            isRowEmpty(row) {
                return this.columnKeys.every(key => !row[key]);
            },

            deleteRow(rowIndex) {
                const row = this.rows[rowIndex];
                if (row._id) {
                    row._deleted = true;
                } else {
                    this.rows.splice(rowIndex, 1);
                    this.ensureEmptyRows();
                }
            },

            undeleteRow(rowIndex) {
                this.rows[rowIndex]._deleted = false;
            },

            addRows(count) {
                for (let i = 0; i < count; i++) {
                    this.rows.push(createEmptyRow(this.columnKeys));
                }
            },

            ensureEmptyRows() {
                let emptyCount = 0;
                for (let i = this.rows.length - 1; i >= 0; i--) {
                    const row = this.rows[i];
                    if (!row._id && this.isRowEmpty(row) && !row._deleted) {
                        emptyCount++;
                    } else {
                        break;
                    }
                }
                while (emptyCount < this.emptyRowCount) {
                    this.rows.push(createEmptyRow(this.columnKeys));
                    emptyCount++;
                }
            },

            /**
             * Create an empty row - exposed for parent components.
             */
            createEmptyRow() {
                return createEmptyRow(this.columnKeys);
            },

            confirmDeleteAll() {
                if (confirm('Are you sure you want to delete ALL rows? This cannot be undone after saving.')) {
                    this.$emit('delete-all');
                }
            }
        }
    }
</script>

<style scoped>
    .spreadsheet-table {
        border-collapse: collapse;
    }
    .spreadsheet-table th {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .spreadsheet-table td {
        padding: 0 !important;
        vertical-align: middle;
    }
    .spreadsheet-table td.row-number {
        padding: 4px 8px !important;
        text-align: center;
        color: #999;
        background-color: #f8f9fa;
        font-size: 0.85em;
    }
    .cell-input {
        width: 100%;
        border: none;
        outline: none;
        padding: 4px 8px;
        background: transparent;
        font-size: 0.9em;
    }
    .cell-input:focus {
        background-color: #fff;
        box-shadow: inset 0 0 0 2px #80bdff;
    }
    .cell-input:disabled {
        color: #999;
        text-decoration: line-through;
    }
    .active-cell {
        background-color: #e8f0fe;
    }
    .spreadsheet-table .btn-sm {
        padding: 0 6px;
        line-height: 1.5;
    }
</style>
