package com.innovatyou.privacydisplay.camera

import android.util.Log
import androidx.camera.core.Camera
import androidx.camera.core.FocusMeteringAction
import androidx.camera.core.SurfaceOrientedMeteringPointFactory
import androidx.camera.core.UseCase
import java.util.concurrent.TimeUnit

/**
 * Points the camera's auto-exposure at the face. Without this the camera exposes for the whole
 * scene, so a bright window or lamp behind the person makes their face come out dark.
 */
object FaceMetering {
    /** How often the metering region is moved to follow the face. */
    const val INTERVAL_MS = 2_000L

    /**
     * @param centerRaw face centre in the analysis buffer (unrotated) coordinates.
     * @param useCase the stream the buffer comes from, so CameraX can map it to the sensor.
     */
    fun meterOn(camera: Camera, centerRaw: Pair<Float, Float>, rawWidth: Int, rawHeight: Int, useCase: UseCase?) {
        if (rawWidth <= 0 || rawHeight <= 0) return
        try {
            val factory = if (useCase != null) {
                SurfaceOrientedMeteringPointFactory(rawWidth.toFloat(), rawHeight.toFloat(), useCase)
            } else {
                SurfaceOrientedMeteringPointFactory(rawWidth.toFloat(), rawHeight.toFloat())
            }
            val point = factory.createPoint(centerRaw.first, centerRaw.second, POINT_SIZE)
            val action = FocusMeteringAction.Builder(point, FocusMeteringAction.FLAG_AE)
                .setAutoCancelDuration(AUTO_CANCEL_S, TimeUnit.SECONDS)
                .build()
            if (camera.cameraInfo.isFocusMeteringSupported(action)) {
                camera.cameraControl.startFocusAndMetering(action)
            }
        } catch (e: Exception) {
            Log.d(TAG, "Face metering not available", e)
        }
    }

    private const val TAG = "FaceMetering"
    private const val POINT_SIZE = 0.3f
    private const val AUTO_CANCEL_S = 5L
}
