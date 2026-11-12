<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Camera, RefreshCw, Check, X, Upload } from 'lucide-vue-next';
import { useI18n } from '../Composables/useI18n';

const props = defineProps({
  title: {
    type: String,
    default: 'Take Photo',
  },
});

const emit = defineEmits(['captured', 'close']);
const { t } = useI18n();

const videoRef = ref(null);
const canvasRef = ref(null);
const fileInputRef = ref(null);
const stream = ref(null);
const capturedImage = ref(null);
const cameraActive = ref(false);
const cameraError = ref(null);
const facingMode = ref('user'); // 'user' or 'environment'

const startCamera = async () => {
  cameraError.value = null;
  stopCamera();

  try {
    const constraints = {
      video: {
        facingMode: facingMode.value,
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
      audio: false,
    };

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Camera not supported on this browser or connection. Please upload an image.');
    }

    const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
    stream.value = mediaStream;
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream;
      await videoRef.value.play();
      cameraActive.value = true;
    }
  } catch (err) {
    cameraError.value = err.message || 'Unable to access camera. Please allow camera access or upload from files.';
    cameraActive.value = false;
  }
};

const stopCamera = () => {
  if (stream.value) {
    stream.value.getTracks().forEach((track) => track.stop());
    stream.value = null;
  }
  cameraActive.value = false;
};

const switchCamera = () => {
  facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
  startCamera();
};

const takeSnapshot = () => {
  if (!videoRef.value || !canvasRef.value) return;

  const video = videoRef.value;
  const canvas = canvasRef.value;
  const context = canvas.getContext('2d');

  // Max width 1200px for optimal speed & quality
  const maxWidth = 1200;
  const scale = Math.min(1, maxWidth / video.videoWidth);
  canvas.width = video.videoWidth * scale;
  canvas.height = video.videoHeight * scale;

  context.drawImage(video, 0, 0, canvas.width, canvas.height);
  const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

  capturedImage.value = dataUrl;
  stopCamera();
};

const retake = () => {
  capturedImage.value = null;
  startCamera();
};

const confirmPhoto = () => {
  if (capturedImage.value) {
    emit('captured', capturedImage.value);
    emit('close');
  }
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    // Compress via canvas
    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      const maxWidth = 1200;
      const scale = Math.min(1, maxWidth / img.width);
      canvas.width = img.width * scale;
      canvas.height = img.height * scale;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      capturedImage.value = canvas.toDataURL('image/jpeg', 0.85);
      stopCamera();
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
};

onMounted(() => {
  startCamera();
});

onBeforeUnmount(() => {
  stopCamera();
});
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
        <div class="flex items-center gap-2">
          <Camera class="w-5 h-5 text-amber-500" />
          <h3 class="font-semibold text-slate-100 text-lg">{{ title }}</h3>
        </div>
        <button
          type="button"
          @click="emit('close')"
          class="p-1 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Body / Viewport -->
      <div class="relative bg-black flex-1 flex items-center justify-center min-h-[300px] overflow-hidden">
        <!-- Live Video -->
        <video
          v-show="!capturedImage && cameraActive"
          ref="videoRef"
          autoplay
          playsinline
          muted
          class="w-full h-full object-cover max-h-[55vh]"
        ></video>

        <!-- Preview of Captured Photo -->
        <div v-if="capturedImage" class="w-full h-full flex items-center justify-center p-2">
          <img
            :src="capturedImage"
            alt="Preview"
            class="max-w-full max-h-[55vh] object-contain rounded-lg border border-slate-800 shadow"
          />
        </div>

        <!-- Camera Error / Permission Fallback -->
        <div v-if="cameraError && !capturedImage" class="p-6 text-center max-w-sm">
          <div class="w-12 h-12 rounded-full bg-red-950/50 border border-red-800 flex items-center justify-center mx-auto mb-3 text-red-400">
            <Camera class="w-6 h-6" />
          </div>
          <p class="text-sm text-red-300 mb-4">{{ cameraError }}</p>
          <button
            type="button"
            @click="fileInputRef?.click()"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium text-sm transition shadow-lg"
          >
            <Upload class="w-4 h-4" />
            {{ t('upload_file', 'Upload from Device') }}
          </button>
        </div>

        <!-- Hidden canvas for snapshotting -->
        <canvas ref="canvasRef" class="hidden"></canvas>
        <input
          ref="fileInputRef"
          type="file"
          accept="image/*"
          capture="environment"
          @change="handleFileUpload"
          class="hidden"
        />
      </div>

      <!-- Footer Actions -->
      <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between gap-3">
        <!-- Left: Upload fallback button -->
        <button
          type="button"
          @click="fileInputRef?.click()"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-700 bg-slate-800/80 hover:bg-slate-800 text-slate-300 text-xs font-medium transition"
        >
          <Upload class="w-3.5 h-3.5 text-slate-400" />
          {{ t('upload_file', 'Upload File') }}
        </button>

        <!-- Center: Action Controls -->
        <div class="flex items-center gap-2">
          <!-- Switch Camera -->
          <button
            v-if="cameraActive && !capturedImage"
            type="button"
            @click="switchCamera"
            title="Switch Camera"
            class="p-2.5 rounded-full border border-slate-700 bg-slate-800 text-slate-300 hover:text-white transition"
          >
            <RefreshCw class="w-4 h-4" />
          </button>

          <!-- Capture Button -->
          <button
            v-if="cameraActive && !capturedImage"
            type="button"
            @click="takeSnapshot"
            class="px-5 py-2.5 rounded-full bg-amber-600 hover:bg-amber-500 text-white font-semibold text-sm flex items-center gap-2 shadow-lg shadow-amber-950 transition active:scale-95"
          >
            <Camera class="w-4 h-4" />
            {{ t('capture', 'Capture Photo') }}
          </button>

          <!-- Retake Button -->
          <button
            v-if="capturedImage"
            type="button"
            @click="retake"
            class="px-4 py-2 rounded-xl border border-slate-700 bg-slate-800 text-slate-300 hover:text-white text-xs font-medium transition"
          >
            {{ t('retake', 'Retake') }}
          </button>

          <!-- Confirm & Use Photo -->
          <button
            v-if="capturedImage"
            type="button"
            @click="confirmPhoto"
            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-950 transition"
          >
            <Check class="w-4 h-4" />
            {{ t('use_photo', 'Use Photo') }}
          </button>
        </div>

        <!-- Right: Cancel -->
        <button
          type="button"
          @click="emit('close')"
          class="text-xs text-slate-400 hover:text-slate-200 transition"
        >
          {{ t('cancel', 'Cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>

