package com.innovatyou.privacydisplay.owner

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import dagger.hilt.android.qualifiers.ApplicationContext
import java.io.File
import java.nio.ByteBuffer
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext

/**
 * Stores the owner's face print (numbers only, never a photo), encrypted with AES-GCM using a key
 * held in the Android Keystore. The file is excluded from backups and never leaves the device.
 */
interface OwnerFaceStore {
    val enrolled: StateFlow<Boolean>
    suspend fun save(embeddings: List<FloatArray>)
    suspend fun load(): List<FloatArray>
    suspend fun delete()
}

@Singleton
class KeystoreOwnerFaceStore @Inject constructor(
    @ApplicationContext context: Context,
) : OwnerFaceStore {
    private val file = File(context.noBackupFilesDir, FILE_NAME)
    private val _enrolled = MutableStateFlow(file.exists())
    override val enrolled: StateFlow<Boolean> = _enrolled.asStateFlow()

    @Volatile private var cache: List<FloatArray>? = null

    override suspend fun save(embeddings: List<FloatArray>) = withContext(Dispatchers.IO) {
        val cipher = Cipher.getInstance(TRANSFORMATION).apply { init(Cipher.ENCRYPT_MODE, key()) }
        val encrypted = cipher.doFinal(FacePrintCodec.encode(embeddings))
        val tmp = File(file.parentFile, "$FILE_NAME.tmp")
        tmp.writeBytes(ByteBuffer.allocate(4 + cipher.iv.size + encrypted.size)
            .putInt(cipher.iv.size).put(cipher.iv).put(encrypted).array())
        tmp.renameTo(file)
        cache = embeddings.map { it.copyOf() }
        _enrolled.value = true
    }

    override suspend fun load(): List<FloatArray> = withContext(Dispatchers.IO) {
        cache?.let { return@withContext it }
        if (!file.exists()) return@withContext emptyList()
        try {
            val buffer = ByteBuffer.wrap(file.readBytes())
            val iv = ByteArray(buffer.int).also { buffer.get(it) }
            val encrypted = ByteArray(buffer.remaining()).also { buffer.get(it) }
            val cipher = Cipher.getInstance(TRANSFORMATION).apply {
                init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(TAG_BITS, iv))
            }
            FacePrintCodec.decode(cipher.doFinal(encrypted)).also { cache = it }
        } catch (e: Exception) {
            // Corrupt file or lost key (e.g. after a device reset): the owner has to set up again.
            emptyList()
        }
    }

    override suspend fun delete() = withContext(Dispatchers.IO) {
        file.delete()
        cache = null
        KeyStore.getInstance(KEYSTORE).apply { load(null) }.deleteEntry(KEY_ALIAS)
        _enrolled.value = false
    }

    private fun key(): SecretKey {
        val keyStore = KeyStore.getInstance(KEYSTORE).apply { load(null) }
        (keyStore.getKey(KEY_ALIAS, null) as? SecretKey)?.let { return it }
        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, KEYSTORE)
        generator.init(
            KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setKeySize(256)
                .build()
        )
        return generator.generateKey()
    }

    private companion object {
        const val FILE_NAME = "owner_face_print.bin"
        const val KEYSTORE = "AndroidKeyStore"
        const val KEY_ALIAS = "privacy_display_owner_face"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
        const val TAG_BITS = 128
    }
}

/** Binary format of the face print: version, count, length, then the floats. */
object FacePrintCodec {
    private const val VERSION = 1

    fun encode(embeddings: List<FloatArray>): ByteArray {
        val length = embeddings.firstOrNull()?.size ?: 0
        require(embeddings.all { it.size == length })
        val buffer = ByteBuffer.allocate(12 + 4 * length * embeddings.size)
        buffer.putInt(VERSION).putInt(embeddings.size).putInt(length)
        embeddings.forEach { e -> e.forEach { buffer.putFloat(it) } }
        return buffer.array()
    }

    fun decode(bytes: ByteArray): List<FloatArray> {
        val buffer = ByteBuffer.wrap(bytes)
        require(buffer.int == VERSION) { "Unknown face print version" }
        val count = buffer.int
        val length = buffer.int
        require(count in 0..64 && length in 0..1024 && bytes.size == 12 + 4 * count * length)
        return List(count) { FloatArray(length) { buffer.float } }
    }
}
