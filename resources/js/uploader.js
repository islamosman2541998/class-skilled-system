// The one upload control of the system (spec §5.4). Loaded only on pages that render <x-uploader>.
import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginImageTransform from 'filepond-plugin-image-transform';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css';

FilePond.registerPlugin(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview,
    FilePondPluginImageExifOrientation,
    FilePondPluginImageResize,
    FilePondPluginImageTransform,
);

const t = (key) => (window.I18N && window.I18N.uploader && window.I18N.uploader[key]) || '';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const extensionOf = (name) => {
    const match = /\.([^.]+)$/.exec(name || '');

    return match ? `.${match[1].toLowerCase()}` : '';
};

const labels = () => ({
    labelIdle: t('idle'),
    labelInvalidField: t('invalid'),
    labelFileWaitingForSize: t('waiting'),
    labelFileSizeNotAvailable: t('size_unknown'),
    labelFileLoading: t('loading'),
    labelFileLoadError: t('load_error'),
    labelFileProcessing: t('uploading'),
    labelFileProcessingComplete: t('uploaded'),
    labelFileProcessingAborted: t('aborted'),
    labelFileProcessingError: t('error'),
    labelFileProcessingRevertError: t('error'),
    labelFileRemoveError: t('error'),
    labelTapToCancel: t('tap_cancel'),
    labelTapToRetry: t('tap_retry'),
    labelTapToUndo: t('tap_undo'),
    labelButtonRemoveItem: t('remove'),
    labelButtonAbortItemLoad: t('cancel'),
    labelButtonRetryItemLoad: t('retry'),
    labelButtonAbortItemProcessing: t('cancel'),
    labelButtonUndoItemProcessing: t('undo'),
    labelButtonRetryItemProcessing: t('retry'),
    labelButtonProcessItem: t('upload'),
    labelFileTypeNotAllowed: t('type_not_allowed'),
    fileValidateTypeLabelExpectedTypes: t('expected_types'),
    labelMaxFileSizeExceeded: t('too_large'),
    labelMaxFileSize: t('max_size'),
});

// Grabs a frame of a video in the browser and sends it as the cover image; no FFmpeg on the server.
const sendPoster = (file, serverId, base) => new Promise((resolve) => {
    const video = document.createElement('video');
    const url = URL.createObjectURL(file);
    const done = () => { URL.revokeObjectURL(url); resolve(); };

    video.muted = true;
    video.preload = 'metadata';
    video.src = url;
    video.onerror = done;
    video.onloadeddata = () => { video.currentTime = Math.min(1, (video.duration || 2) / 2); };
    video.onseeked = () => {
        const scale = Math.min(1, 1280 / (video.videoWidth || 1280));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round((video.videoWidth || 1280) * scale);
        canvas.height = Math.round((video.videoHeight || 720) * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) { done(); return; }
            const data = new FormData();
            data.append('poster', blob, 'poster.jpg');
            fetch(`${base}/${serverId}/poster`, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrf() } }).finally(done);
        }, 'image/jpeg', 0.8);
    };
});

function mount(input) {
    const config = JSON.parse(input.dataset.uploader);
    const form = input.closest('form');
    let busy = 0;

    const pond = FilePond.create(input, {
        ...labels(),
        credits: false,
        allowMultiple: input.multiple,
        storeAsFile: false,
        allowPaste: true,
        allowRevert: true,
        instantUpload: true,
        chunkUploads: true,
        chunkSize: config.chunkBytes,
        chunkRetryDelays: [500, 1000, 3000],
        maxFileSize: config.maxBytes,
        acceptedFileTypes: config.accept,
        // Types are checked by extension here and by real content on the server.
        fileValidateTypeDetectType: (file) => Promise.resolve(extensionOf(file.name)),
        captureMethod: config.camera ? 'environment' : null,
        imageResizeTargetWidth: config.maxDimension,
        imageResizeTargetHeight: config.maxDimension,
        imageResizeMode: 'contain',
        imageResizeUpscale: false,
        imageTransformOutputQuality: 82,
        imagePreviewHeight: 140,
        server: {
            process: {
                url: config.process,
                method: 'POST',
                // Header values must be Latin-1, so the original (often Arabic) name travels URL-encoded.
                headers: (file) => ({
                    'X-CSRF-TOKEN': csrf(),
                    'Upload-Length': file.size,
                    'Upload-Name': encodeURIComponent(file.name),
                }),
                // Whole uploads pass the response text; chunked uploads pass the XHR itself.
                onload: (response) => String(typeof response === 'string' ? response : (response?.responseText ?? '')).trim(),
            },
            patch: {
                url: `${config.base}/`,
                headers: (chunk) => ({
                    'X-CSRF-TOKEN': csrf(),
                    'Content-Type': 'application/offset+octet-stream',
                    'Upload-Offset': chunk.offset,
                }),
            },
            revert: (serverId, load, error) => {
                fetch(`${config.base}/${serverId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf() } })
                    .then(() => load()).catch(() => error(t('error')));
            },
            restore: null,
            load: null,
            fetch: null,
        },
    });

    pond.on('processfilestart', () => { busy += 1; });
    pond.on('processfile', (error, item) => {
        busy = Math.max(0, busy - 1);

        if (!error && item.file && /^video\//.test(item.file.type)) {
            sendPoster(item.file, item.serverId, config.base);
        }
    });
    pond.on('processfileabort', () => { busy = Math.max(0, busy - 1); });

    // A form is not sent while a file is still on its way.
    form?.addEventListener('submit', (event) => {
        if (busy > 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.toast?.('warning', t('wait'));
        }
    }, true);
}

document.querySelectorAll('input[data-uploader]').forEach(mount);
